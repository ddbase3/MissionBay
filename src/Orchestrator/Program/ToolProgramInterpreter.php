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
	 * @return array{call:AiToolCall}|array{result:mixed}
	 */
	public function advance(ToolProgram $program, ToolProgramState $state, string $programId, callable $isCancelled): array {
		if ($state->waitingCallId !== '') {
			throw new \LogicException('Program is waiting for a tool result.');
		}
		while (true) {
			if ($isCancelled()) {
				throw new \RuntimeException('Program execution was cancelled.');
			}
			if ($state->steps >= ToolProgram::MAX_STEPS) {
				throw new \RuntimeException('Program instruction limit exceeded.');
			}
			$state->steps++;
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
					if (!$this->boolean($this->pop($state))) { $state->pc = $arg; }
					break;
				case 'and':
				case 'or':
					$value = $this->boolean($this->pop($state));
					if ($value === ($instruction[0] === 'or')) {
						$state->stack[] = $value;
						$state->pc = $arg;
					}
					break;
				case 'boolean': $state->stack[] = $this->boolean($this->pop($state)); break;
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
				case 'append':
					$array = $this->local($state, $arg);
					if (!is_array($array)) { throw new \RuntimeException('push requires an array variable.'); }
					$array[] = $this->pop($state);
					$state->locals[$arg] = $array;
					break;
				case 'iter':
					$array = $this->pop($state);
					if (!is_array($array)) { throw new \RuntimeException('for-of requires an array.'); }
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
					$state->stack[] = $arg === '!' ? !$this->boolean($value) : -$this->number($value);
					break;
				case 'binary':
					$right = $this->pop($state);
					$state->stack[] = $this->binary($arg, $this->pop($state), $right);
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
				case 'halt': throw new \RuntimeException('Program ended without a return value.');
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
		$value = $result->toArray();
		$value['arguments'] = (object)$value['arguments'];
		$value['metadata'] = (object)array_intersect_key($result->getMetadata(), array_flip(['label', 'iteration', 'call_index']));
		$json = json_encode($value, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
		$state->resultBytes += strlen($json);
		if ($state->resultBytes > ToolProgram::MAX_TOOL_RESULT_BYTES) {
			throw new \RuntimeException('Program cumulative tool result limit exceeded.');
		}
		$state->stack[] = json_decode($json, false, 128, JSON_THROW_ON_ERROR);
		$state->checkSize();
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

	private function boolean(mixed $value): bool {
		if (!is_bool($value)) { throw new \RuntimeException('A condition must evaluate to a boolean.'); }
		return $value;
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
		throw new \RuntimeException('Program attempted to read a missing or invalid JSON property.');
	}

	private function binary(string $operator, mixed $left, mixed $right): mixed {
		if (in_array($operator, ['===', '!=='], true)) {
			if (is_object($left) || is_object($right) || is_array($left) || is_array($right)) {
				throw new \RuntimeException('Compare scalar values, not entire arrays or objects.');
			}
			$equal = (is_int($left) || is_float($left)) && (is_int($right) || is_float($right))
				? $left == $right : $left === $right;
			return $operator === '===' ? $equal : !$equal;
		}
		if ($operator === '+' && is_string($left) && is_string($right)) { return $left . $right; }
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
