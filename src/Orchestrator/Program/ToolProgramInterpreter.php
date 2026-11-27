<?php declare(strict_types=1);

namespace Base3\MissionBay\Orchestrator\Program;

use Base3\AssistantFoundation\Dto\AgentToolResult;
use Base3\AssistantFoundation\Dto\AiToolCall;

/** A deterministic JSON-value VM. Tool requests yield to the normal execution stage. */
final class ToolProgramInterpreter {

	/**
	 * Executes until return or until one concrete tool call is needed.
	 *
	 * @param callable():bool $isCancelled
	 * @param callable(string,array):AgentToolResult|null $catalogOperation Successful internal catalog result; invalid operations throw and abort the program.
	 * @return array{call:AiToolCall}|array{result:mixed}
	 */
	public function advance(ToolProgram $program, ToolProgramState $state, string $programId, callable $isCancelled, ?callable $catalogOperation = null): array {
		if ($state->waitingCallId !== '') {
			throw new \LogicException('Program is waiting for a tool result.');
		}
		while (true) {
			$this->step($state, $isCancelled);
			$instruction = $program->instructions[$state->pc++] ?? null;
			if ($instruction === null) {
				throw new \RuntimeException('Invalid program instruction position.');
			}
			$arg = $instruction[1] ?? null;
			switch ($instruction[0]) {
				case 'literal': $state->stack[] = $arg; break;
				case 'load': $state->stack[] = $this->local($state, $arg); break;
				case 'store': $state->locals[$arg] = $this->pop($state); break;
				case 'unset': unset($state->locals[$arg]); break;
				case 'pop': $this->pop($state); break;
				case 'jump': $state->pc = $arg; break;
				case 'branch':
					if (!$this->truthy($this->pop($state))) { $state->pc = $arg; }
					break;
				case 'and':
				case 'or':
					$value = $this->pop($state);
					if ($this->truthy($value) === ($instruction[0] === 'or')) {
						$state->stack[] = $value;
						$state->pc = $arg;
					}
					break;
				case 'scalar':
					$value = $this->pop($state);
					if (is_array($value) || is_object($value)) { throw new \RuntimeException('includes requires a scalar search value. Use some(item => item.id === id) for objects.'); }
					$state->stack[] = $value;
					break;
				case 'string_scalar': $state->stack[] = $this->stringScalar($this->pop($state)); break;
				case 'stringify':
					$state->stack[] = json_encode($this->pop($state), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
					break;
				case 'array': $state->stack[] = $this->popMany($state, $arg); break;
				case 'object':
					$values = $this->popMany($state, count($arg));
					$object = new \stdClass();
					foreach ($arg as $index => $key) { $object->{$key} = $values[$index]; }
					$state->stack[] = $object;
					break;
				case 'get':
					$key = $this->pop($state);
					$state->stack[] = $this->property($this->pop($state), $key);
					break;
				case 'join':
					$separator = $this->pop($state);
					$state->stack[] = $this->join($this->pop($state), $separator);
					break;
				case 'sort':
					$state->locals[$arg] = $this->sort($this->local($state, $arg), $state, $isCancelled);
					$state->stack[] = $state->locals[$arg];
					break;
				case 'append':
					$array = $this->local($state, $arg);
					if (!is_array($array)) { throw new \RuntimeException('push requires an array variable.'); }
					$array[] = $this->pop($state);
					$state->locals[$arg] = $array;
					break;
				case 'iter':
					$array = $this->pop($state);
					if (!is_array($array)) {
						$operation = $instruction[3] ?? 'for-of';
						throw new \RuntimeException($operation . ' requires an array. Check the declared result envelope and array property.');
					}
					$state->locals[$arg] = $array;
					$state->locals[$instruction[2]] = 0;
					break;
				case 'next':
					$array = $this->local($state, $instruction[2]);
					$index = $this->local($state, $instruction[3]);
					if ($index >= count($array)) {
						$state->pc = $arg;
					} else {
						$state->locals[$instruction[4]] = $array[$index];
						$state->locals[$instruction[3]] = $index + 1;
					}
					break;
				case 'unary':
					$value = $this->pop($state);
					$state->stack[] = $arg === '!' ? !$this->truthy($value) : -$this->number($value);
					break;
				case 'increment':
					$state->locals[$arg] = $this->number($this->local($state, $arg)) + $instruction[2];
					break;
				case 'binary':
					$right = $this->pop($state);
					$state->stack[] = $this->binary($arg, $this->pop($state), $right);
					break;
				case 'catalog':
					if ($catalogOperation === null) { throw new \RuntimeException('Catalog exploration is not available in this program.'); }
					$args = $this->pop($state);
					if (!$args instanceof \stdClass) { throw new \RuntimeException('Catalog arguments must be a JSON object.'); }
					$result = $catalogOperation($arg, json_decode(json_encode($args, JSON_THROW_ON_ERROR), true, 128, JSON_THROW_ON_ERROR));
					$this->pushResult($state, $result);
					break;
				case 'call':
					if ($state->calls >= ToolProgram::MAX_CALLS) {
						throw new \RuntimeException('Program tool call limit exceeded.');
					}
					$args = $this->pop($state);
					if (!$args instanceof \stdClass) {
						throw new \RuntimeException('Tool arguments must be a JSON object.');
					}
					$arguments = json_decode(json_encode($args, JSON_THROW_ON_ERROR), true, 128, JSON_THROW_ON_ERROR);
					$state->calls++;
					$state->waitingCallId = 'program-' . hash('sha256', $programId . ':' . $state->calls);
					$state->checkSize();
					return ['call' => new AiToolCall($state->waitingCallId, $arg, $arguments, [
						'program_call_id' => $programId,
						'program_step' => $state->calls
					])];
				case 'return':
					$result = $this->pop($state);
					if (strlen(json_encode($result, JSON_THROW_ON_ERROR)) > ToolProgram::MAX_RESULT_BYTES) {
						throw new \RuntimeException('Program return value exceeds 64 KiB. Return a compact summary.');
					}
					return ['result' => $result];
				case 'halt': throw new \RuntimeException('Program ended without a return value. Use return expression; or return null; to finish each execution path.');
				default: throw new \RuntimeException('Invalid program instruction.');
			}
			// Account for amplified values, including arrays and strings, before advancing.
			$state->checkSize();
		}
	}

	/** Accepts exactly the outstanding result. No earlier call can be replayed. */
	public function acceptResult(ToolProgramState $state, AgentToolResult $result): void {
		if ($state->waitingCallId === '' || $state->waitingCallId !== $result->getCallId()) {
			throw new \RuntimeException('Program tool result does not match the pending call.');
		}
		$state->waitingCallId = '';
		if ($result->isSuccess()) { $state->successful++; } else { $state->failed++; }
		$this->pushResult($state, $result);
		$state->checkSize();
	}

	/** Serialize domain and internal catalog results through the same JSON envelope and byte budget. */
	private function pushResult(ToolProgramState $state, AgentToolResult $result): void {
		$value = $result->toArray();
		$value['arguments'] = (object)$value['arguments'];
		$value['metadata'] = (object)array_intersect_key($result->getMetadata(), array_flip(['label', 'iteration', 'call_index']));
		$json = json_encode($value, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
		$state->resultBytes += strlen($json);
		if ($state->resultBytes > ToolProgram::MAX_TOOL_RESULT_BYTES) {
			throw new \RuntimeException('Program cumulative tool result limit exceeded.');
		}
		$state->stack[] = json_decode($json, false, 128, JSON_THROW_ON_ERROR);
	}

	private function step(ToolProgramState $state, callable $isCancelled): void {
		if ($isCancelled()) { throw new \RuntimeException('Program execution was cancelled.'); }
		if ($state->steps >= ToolProgram::MAX_STEPS) { throw new \RuntimeException('Program instruction limit exceeded.'); }
		$state->steps++;
	}

	/**
	 * Sort a local JSON scalar array by the UTF-8 bytes of its scalar text.
	 * Charge key preparation and each comparison to the existing VM budget.
	 */
	private function sort(mixed $values, ToolProgramState $state, callable $isCancelled): array {
		if (!is_array($values)) { throw new \RuntimeException('sort requires a local array of JSON scalars.'); }
		$entries = [];
		foreach ($values as $value) {
			$this->step($state, $isCancelled);
			if (is_array($value) || is_object($value)) { throw new \RuntimeException('sort requires scalar array elements; map objects to scalar keys first.'); }
			$key = $this->stringScalar($value);
			$state->chargeWork(strlen($key));
			$entries[] = ['key' => $key, 'value' => $value];
		}
		usort($entries, function(array $a, array $b) use ($state, $isCancelled): int {
			$this->step($state, $isCancelled);
			$state->chargeWork(strlen($a['key']) + strlen($b['key']));
			return strcmp($a['key'], $b['key']);
		});
		return array_column($entries, 'value');
	}

	private function local(ToolProgramState $state, string $slot): mixed {
		if (!array_key_exists($slot, $state->locals)) { throw new \RuntimeException('Uninitialized program variable.'); }
		return $state->locals[$slot];
	}

	private function pop(ToolProgramState $state): mixed {
		if ($state->stack === []) { throw new \RuntimeException('Program operand stack is empty.'); }
		return array_pop($state->stack);
	}

	private function popMany(ToolProgramState $state, int $count): array {
		if ($count > count($state->stack)) { throw new \RuntimeException('Program operand stack is incomplete.'); }
		return $count === 0 ? [] : array_splice($state->stack, -$count);
	}

	private function truthy(mixed $value): bool {
		// JavaScript truthiness for the VM's JSON value domain; PHP treats "0" and [] differently.
		return $value !== null && $value !== false && $value !== '' && $value !== 0 && $value !== 0.0;
	}

	/** Template interpolation is limited to JSON scalars. */
	private function stringScalar(mixed $value): string {
		if (is_string($value)) { return $value; }
		if ($value === null || is_bool($value)) { return json_encode($value, JSON_THROW_ON_ERROR); }
		if (is_int($value) || is_float($value)) { return json_encode($this->number($value), JSON_THROW_ON_ERROR); }
		throw new \RuntimeException('Template interpolation requires a scalar; use a declared property or JSON.stringify(value).');
	}

	private function number(mixed $value): int|float {
		if ((!is_int($value) && !is_float($value)) || !is_finite((float)$value)) { throw new \RuntimeException('Arithmetic requires finite numbers.'); }
		return $value;
	}

	private function property(mixed $value, mixed $key): mixed {
		if ($value instanceof \stdClass && (is_string($key) || is_int($key)) && property_exists($value, (string)$key)) {
			return $value->{(string)$key};
		}
		if (is_array($value)) {
			if ($key === 'length') { return count($value); }
			if (is_int($key) && array_key_exists($key, $value)) { return $value[$key]; }
		}
		$property = $this->diagnosticPropertyName($key) ?? '[non-identifier key omitted]';
		$hint = ' Check the actual output schema and result envelope; missing properties are not implicit null values.';
		if ($value instanceof \stdClass) {
			$names = [];
			$omitted = false;
			$seen = 0;
			foreach ($value as $name => $unused) {
				if (++$seen > 12) { $omitted = true; break; }
				$label = $this->diagnosticPropertyName($name);
				if ($label === null) { $omitted = true; continue; }
				$names[] = $label;
			}
			$available = $names === [] ? 'none' : implode(', ', $names);
			if ($omitted) { $available .= ' (additional names omitted)'; }
			throw new \RuntimeException('Unknown or invalid JSON object property ' . $property . '. Available properties: ' . $available . '.' . $hint);
		}
		if (is_array($value)) {
			$indices = $value === [] ? 'no elements' : 'integer indices 0 to ' . (count($value) - 1);
			throw new \RuntimeException('Invalid JSON array property ' . $property . '. Use "length" or an existing integer index (' . $indices . ').' . $hint);
		}
		throw new \RuntimeException('Cannot read JSON property ' . $property . ' from ' . get_debug_type($value) . '.' . $hint);
	}

	/** Bound diagnostics to short identifier-like keys or integer indices; never include field values. */
	private function diagnosticPropertyName(mixed $key): ?string {
		if (is_int($key)) { return (string)$key; }
		if (!is_string($key) || strlen($key) > 64 || preg_match('/^[A-Za-z_][A-Za-z0-9_-]*$/D', $key) !== 1) { return null; }
		return '"' . $key . '"';
	}

	/** Joins only strings and finite numbers, without generic JSON value coercion. */
	private function join(mixed $values, mixed $separator): string {
		if (!is_array($values) || !is_string($separator)) {
			throw new \RuntimeException('join requires an array and a string separator.');
		}
		$result = '';
		$bytes = 0;
		$separatorBytes = strlen($separator);
		foreach ($values as $index => $value) {
			if (is_string($value)) {
				$part = $value;
			} elseif ((is_int($value) || is_float($value)) && is_finite((float)$value)) {
				$part = json_encode($value, JSON_THROW_ON_ERROR);
			} else {
				throw new \RuntimeException('join requires strings or finite numbers as array elements.');
			}
			$bytes += strlen($part) + ($index === 0 ? 0 : $separatorBytes);
			// Bound amplification before allocating a string larger than the VM state budget.
			if ($bytes > ToolProgram::MAX_STATE_BYTES) {
				throw new \RuntimeException('Joined string exceeds the program state limit.');
			}
			if ($index !== 0) { $result .= $separator; }
			$result .= $part;
		}
		return $result;
	}

	private function binary(string $operator, mixed $left, mixed $right): mixed {
		if (in_array($operator, ['===', '!=='], true)) {
			$leftComposite = is_object($left) || is_array($left);
			$rightComposite = is_object($right) || is_array($right);
			if ($leftComposite && $rightComposite) {
				throw new \RuntimeException('Compare scalar values or declared object properties, not entire arrays or objects.');
			}
			// JSON objects and arrays are never strictly equal to a scalar, including null.
			$equal = !$leftComposite && !$rightComposite && ((is_int($left) || is_float($left)) && (is_int($right) || is_float($right))
				? $left == $right : $left === $right);
			return $operator === '===' ? $equal : !$equal;
		}
		if ($operator === '+' && (is_string($left) || is_string($right))) {
			// Concatenate strings and finite numbers without coercing other JSON values.
			$left = is_string($left) ? $left : json_encode($this->number($left), JSON_THROW_ON_ERROR);
			$right = is_string($right) ? $right : json_encode($this->number($right), JSON_THROW_ON_ERROR);
			return $left . $right;
		}
		$left = $this->number($left);
		$right = $this->number($right);
		return match ($operator) {
			'+' => $left + $right, '-' => $left - $right, '*' => $left * $right,
			'/' => $left / $right, '%' => fmod($left, $right),
			'<' => $left < $right, '<=' => $left <= $right, '>' => $left > $right, '>=' => $left >= $right,
			default => throw new \RuntimeException('Unsupported program operator.')
		};
	}
}
