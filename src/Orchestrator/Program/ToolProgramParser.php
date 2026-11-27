<?php declare(strict_types=1);

namespace Base3\MissionBay\Orchestrator\Program;

/** Compiles the documented restricted JavaScript grammar to bounded VM instructions. */
final class ToolProgramParser {

	private array $tokens = [];
	private string $source = '';
	private int $callbackDepth = 0;
	private int $position = 0;
	private array $code = [];
	private array $tools = [];
	private array $catalogOperations = [];
	private array $scopes = [];
	private array $loops = [];
	private int $nextSlot = 0;
	private int $depth = 0;

	private const PRECEDENCE = [
		'||' => 1, '&&' => 2,
		'===' => 3, '!==' => 3,
		'<' => 4, '<=' => 4, '>' => 4, '>=' => 4,
		'+' => 5, '-' => 5, '*' => 6, '/' => 6, '%' => 6
	];

	public function parse(string $source): ToolProgram {
		if ($source === '' || strlen($source) > ToolProgram::MAX_SOURCE_BYTES) {
			throw new \InvalidArgumentException('Program source is empty or exceeds 64 KiB.');
		}
		$this->source = $source;
		$this->tokens = $this->tokenize($source);
		$this->callbackDepth = 0;
		$this->position = 0;
		$this->code = [];
		$this->tools = [];
		$this->catalogOperations = [];
		$this->scopes = [[]];
		$this->loops = [];
		$this->nextSlot = 0;
		$this->depth = 0;
		while ($this->peek() !== '') {
			$this->statement();
		}
		if (!in_array('return', array_column($this->code, 0), true)) { $this->error('Program has no return statement; use return expression; or return null;'); }
		$this->emit('halt');
		return new ToolProgram($this->code, array_keys($this->tools), array_keys($this->catalogOperations));
	}

	private function tokenize(string $source): array {
		$tokens = [];
		$offset = 0;
		$this->tokenizeExpression($source, $offset, $tokens, 0, false);
		return $tokens;
	}

	private function tokenizeExpression(string $source, int &$offset, array &$tokens, int $depth, bool $interpolation): void {
		$pattern = <<<'REGEX'
~\G(?:\s+|//[^\r\n]*|/\*[\s\S]*?\*/|"(?:[^"\\\x00-\x1f]|\\["\\/bfnrt]|\\u[0-9a-fA-F]{4})*"|'(?:[^'\\\x00-\x1f]|\\['"\\/bfnrt]|\\u[0-9a-fA-F]{4})*'|(?:0|[1-9][0-9]*)(?:\.[0-9]+)?(?:[eE][+-]?[0-9]+)?|[A-Za-z_$][A-Za-z0-9_$]*|===|!==|=>|<=|>=|&&|\|\||\+\+|--|\+=|-=|\*=|/=|%=|==|!=|[{}\[\]();:?,.=+*/%<>!\-])~
REGEX;
		$braces = 0;
		while ($offset < strlen($source)) {
			if ($source[$offset] === '`') {
				$this->tokenizeTemplate($source, $offset, $tokens, $depth + 1);
				continue;
			}
			if (preg_match($pattern, $source, $match, 0, $offset) !== 1) {
				$this->errorAt('Unsupported program syntax; use the documented restricted JavaScript constructs', $offset);
			}
			$text = $match[0];
			if (!ctype_space($text[0]) && !str_starts_with($text, '//') && !str_starts_with($text, '/*')) {
				$tokens[] = [$text, $offset];
				if ($interpolation) {
					if ($text === '{') { $braces++; }
					if ($text === '}' && $braces-- === 0) {
						$offset++;
						return;
					}
				}
			}
			$offset += strlen($text);
		}
		if ($interpolation) { $this->errorAt('Unterminated template interpolation; expected }', $offset); }
	}

	/** Emit literal JSON-string tokens alternating with ordinary expression tokens. */
	private function tokenizeTemplate(string $source, int &$offset, array &$tokens, int $depth): void {
		if ($depth > ToolProgram::MAX_DEPTH) { $this->errorAt('Program nesting limit exceeded', $offset); }
		$tokens[] = ['`', $offset++];
		$start = $offset;
		$text = '';
		while ($offset < strlen($source)) {
			$char = $source[$offset];
			if ($char === '`' || substr($source, $offset, 2) === '${') {
				try {
					json_decode('"' . $text . '"', true, 4, JSON_THROW_ON_ERROR);
				} catch (\JsonException) {
					$this->errorAt('Invalid template string escape or UTF-8', $start);
				}
				$tokens[] = ['"' . $text . '"', $start];
				if ($char === '`') {
					$tokens[] = ['`', $offset++];
					return;
				}
				$tokens[] = ['${', $offset];
				$offset += 2;
				$this->tokenizeExpression($source, $offset, $tokens, $depth, true);
				$start = $offset;
				$text = '';
				continue;
			}
			if ($char === '\\') {
				$escape = $source[++$offset] ?? '';
				if ($escape === '`' || $escape === '$' || $escape === "'") {
					$text .= $escape;
				} elseif ($escape === 'u') {
					$digits = substr($source, $offset + 1, 4);
					if (strlen($digits) !== 4 || !ctype_xdigit($digits)) { $this->errorAt('Invalid template Unicode escape', $offset); }
					$text .= '\\u' . $digits;
					$offset += 4;
				} elseif ($escape !== '' && str_contains('"\\/bfnrt', $escape)) {
					$text .= '\\' . $escape;
				} else {
					$this->errorAt('Unsupported template escape', $offset);
				}
			} else {
				if ($char === "\r") {
					if (($source[$offset + 1] ?? '') === "\n") { $offset++; }
					$char = "\n";
				}
				$text .= match ($char) {'"' => '\\"', "\n" => '\\n', "\r" => '\\r', "\t" => '\\t', default => $char};
			}
			$offset++;
		}
		$this->errorAt('Unterminated template literal; expected closing backtick', $offset);
	}

	private function statement(): void {
		$this->enter();
		try {
			$this->parseStatement();
		} finally {
			$this->depth--;
		}
	}

	private function parseStatement(): void {
		if ($this->accept('let')) {
			$name = $this->identifier();
			$this->expect('=');
			$this->expression();
			$this->emit('store', $this->declare($name));
			$this->expect(';');
			return;
		}
		if ($this->accept('if')) {
			$this->expect('(');
			$this->expression();
			$this->expect(')');
			$falseJump = $this->emit('branch', 0);
			$this->block();
			if ($this->accept('else')) {
				$endJump = $this->emit('jump', 0);
				$this->patch($falseJump, count($this->code));
				if ($this->peek() === 'if') {
					$this->statement();
				} else {
					$this->block();
				}
				$this->patch($endJump, count($this->code));
			} else {
				$this->patch($falseJump, count($this->code));
			}
			return;
		}
		if ($this->accept('for')) {
			$this->expect('(');
			$this->expect('let');
			$name = $this->identifier();
			if ($this->accept('of')) {
				$this->expression();
				$this->expect(')');
				$sequence = $this->slot();
				$cursor = $this->slot();
				$this->emit('iter', $sequence, $cursor);
				$this->scopes[] = [];
				$variable = $this->declare($name);
				$start = count($this->code);
				$next = $this->emit('next', 0, $sequence, $cursor, $variable);
				$this->loops[] = ['continue' => $start, 'breaks' => [], 'scope' => array_key_last($this->scopes)];
				$this->block();
				$this->emit('jump', $start);
				$end = count($this->code);
				$this->patch($next, $end);
				$this->finishLoop($end);
				$this->emit('unset', $sequence);
				$this->emit('unset', $cursor);
				$this->leaveScope();
			} elseif ($this->accept('=')) {
				$this->scopes[] = [];
				$this->expression();
				$this->emit('store', $this->declare($name));
				$this->expect(';');
				$condition = count($this->code);
				$this->expression();
				$this->expect(';');
				$endJump = $this->emit('branch', 0);
				$bodyJump = $this->emit('jump', 0);
				$update = count($this->code);
				if (!$this->localUpdate()) { $this->error('Expected a local variable update in for loop (i++, i += 1 or i = i + 1)'); }
				$this->expect(')');
				$this->emit('jump', $condition);
				$this->patch($bodyJump, count($this->code));
				$this->loops[] = ['continue' => $update, 'breaks' => [], 'scope' => array_key_last($this->scopes)];
				$this->block();
				$this->emit('jump', $update);
				$end = count($this->code);
				$this->patch($endJump, $end);
				$this->finishLoop($end);
				$this->leaveScope();
			} else {
				$this->error('Expected for (let item of array) or for (let i = start; condition; update)');
			}
			return;
		}
		if ($this->accept('break') || $this->accept('continue')) {
			$keyword = $this->tokens[$this->position - 1][0];
			if ($this->loops === []) {
				$this->error('Loop control outside a loop');
			}
			$loopIndex = array_key_last($this->loops);
			for ($i = array_key_last($this->scopes); $i > $this->loops[$loopIndex]['scope']; $i--) {
				foreach ($this->scopes[$i] as $slot) { $this->emit('unset', $slot); }
			}
			if ($keyword === 'break') {
				$this->loops[$loopIndex]['breaks'][] = $this->emit('jump', 0);
			} else {
				$this->emit('jump', $this->loops[$loopIndex]['continue']);
			}
			$this->expect(';');
			return;
		}
		if ($this->accept('return')) {
			if ($this->peek() === ';' || $this->peek() === '' || $this->peek() === '}') { $this->error('return requires a JSON value; use return null; if no value is needed'); }
			$this->expression();
			$this->expect(';');
			$this->emit('return');
			return;
		}
		if ($this->peek() === '{') {
			if ($this->peek(2) === ':') { $this->error('An object at statement position is not a return; use return {key: value};'); }
			$this->block();
			return;
		}
		if ($this->localUpdate()) {
			$this->expect(';');
			return;
		}
		if ($this->peek(1) === '.' && $this->peek(2) === 'push' && $this->peek(3) === '(') {
			$slot = $this->lookup($this->identifier());
			$this->expect('.');
			$this->expect('push');
			$this->expect('(');
			$this->expression();
			$this->expect(')');
			$this->expect(';');
			$this->emit('append', $slot);
			return;
		}
		$this->expression();
		$this->expect(';');
		$this->emit('pop');
	}

	private function finishLoop(int $end): void {
		$loop = array_pop($this->loops);
		foreach ($loop['breaks'] as $jump) { $this->patch($jump, $end); }
	}

	/** Only local variable updates, never property assignment or host mutation. */
	private function localUpdate(): bool {
		$prefix = in_array($this->peek(), ['++', '--'], true);
		$operator = $prefix ? $this->peek() : $this->peek(1);
		if (!in_array($operator, ['=', '++', '--', '+=', '-=', '*=', '/=', '%='], true)) { return false; }
		if ($prefix) { $this->take(); }
		$slot = $this->lookup($this->identifier());
		if (!$prefix) { $this->take(); }
		if ($operator === '++' || $operator === '--') {
			$this->emit('increment', $slot, $operator === '++' ? 1 : -1);
			return true;
		}
		if ($operator !== '=') { $this->emit('load', $slot); }
		$this->expression();
		if ($operator !== '=') { $this->emit('binary', $operator[0]); }
		$this->emit('store', $slot);
		return true;
	}

	private function block(): void {
		$this->expect('{');
		$this->scopes[] = [];
		while (!$this->accept('}')) {
			if ($this->peek() === '') {
				$this->error('Unterminated block');
			}
			$this->statement();
		}
		$this->leaveScope();
	}

	private function leaveScope(): void {
		foreach (array_pop($this->scopes) as $slot) {
			$this->emit('unset', $slot);
		}
	}

	private function expression(int $minimum = 1): void {
		$this->enter();
		try {
			$this->prefix();
			while (isset(self::PRECEDENCE[$this->peek()]) && self::PRECEDENCE[$this->peek()] >= $minimum) {
				$operator = $this->take();
				$precedence = self::PRECEDENCE[$operator];
				if ($operator === '&&' || $operator === '||') {
					$jump = $this->emit($operator === '&&' ? 'and' : 'or', 0);
					$this->expression($precedence + 1);
					$this->patch($jump, count($this->code));
				} else {
					$this->expression($precedence + 1);
					$this->emit('binary', $operator);
				}
			}
			// Conditional expressions bind below all binary operators and associate to the right.
			if ($minimum === 1 && $this->accept('?')) {
				$falseJump = $this->emit('branch', 0);
				$this->expression();
				$this->expect(':');
				$endJump = $this->emit('jump', 0);
				$this->patch($falseJump, count($this->code));
				$this->expression();
				$this->patch($endJump, count($this->code));
			}
		} finally {
			$this->depth--;
		}
	}

	private function prefix(): void {
		if ($this->peek(1) === '=>') { $this->error('Arrow functions are only supported as expression callbacks in find, map, filter, some and every; otherwise use a for-of loop'); }
		if ($this->accept('!') || $this->accept('-')) {
			$operator = $this->tokens[$this->position - 1][0];
			$this->expression(7);
			$this->emit('unary', $operator);
			return;
		}
		if ($this->accept('`')) {
			$this->emit('literal', $this->string($this->take()));
			while ($this->accept('${')) {
				$this->expression();
				$this->expect('}');
				$this->emit('string_scalar');
				$this->emit('binary', '+');
				$this->emit('literal', $this->string($this->take()));
				$this->emit('binary', '+');
			}
			$this->expect('`');
		} elseif ($this->accept('(')) {
			$this->expression();
			$this->expect(')');
		} elseif ($this->accept('[')) {
			$count = 0;
			if (!$this->accept(']')) {
				do {
					$this->expression();
					$count++;
				} while ($this->accept(',') && $this->peek() !== ']');
				$this->expect(']');
			}
			$this->emit('array', $count);
		} elseif ($this->accept('{')) {
			$keys = [];
			if (!$this->accept('}')) {
				do {
					$token = $this->peek();
					$key = $this->isString($token) ? $this->string($this->take()) : $this->identifier();
					if (in_array($key, $keys, true)) {
						$this->error('Duplicate object key');
					}
					$keys[] = $key;
					$this->expect(':');
					$this->expression();
				} while ($this->accept(',') && $this->peek() !== '}');
				$this->expect('}');
			}
			$this->emit('object', $keys);
		} elseif ($this->peek() === 'JSON' && $this->peek(1) === '.' && $this->peek(2) === 'stringify' && $this->peek(3) === '(') {
			$this->expect('JSON');
			$this->expect('.');
			$this->expect('stringify');
			$this->expect('(');
			$this->expression();
			$this->expect(')');
			$this->emit('stringify');
		} elseif ($this->peek() === 'catalog' && $this->peek(1) === '.' && $this->peek(3) === '(') {
			if ($this->callbackDepth > 0) { $this->error('Array callbacks must be pure expressions; use a for-of loop for catalog operations'); }
			$this->take();
			$this->expect('.');
			$name = $this->identifier();
			if (!in_array($name, ['search', 'select'], true)) { $this->error('Unsupported catalog operation'); }
			$this->expect('(');
			$this->expression();
			$this->expect(')');
			$this->catalogOperations[$name] = true;
			$this->emit('catalog', $name);
		} elseif ($this->accept('tools')) {
			if ($this->callbackDepth > 0) { $this->error('Array callbacks must be pure expressions; use a for-of loop for tool calls'); }
			if ($this->accept('.')) {
				$name = $this->identifier();
			} else {
				$this->expect('[');
				$name = $this->string($this->take());
				$this->expect(']');
			}
			$this->expect('(');
			$this->expression();
			$this->expect(')');
			if ($name === '') {
				$this->error('Empty tool name');
			}
			$this->tools[$name] = true;
			$this->emit('call', $name);
		} elseif ($this->peek(1) === '.' && $this->peek(2) === 'sort' && $this->peek(3) === '(') {
			if ($this->callbackDepth > 0) { $this->error('sort mutates a local array and is not allowed in pure callbacks'); }
			$slot = $this->lookup($this->identifier());
			$this->expect('.');
			$this->expect('sort');
			$this->expect('(');
			if (!$this->accept(')')) { $this->error('sort requires no comparator; only default scalar sorting is supported'); }
			$this->emit('sort', $slot);
		} else {
			$token = $this->take();
			if ($this->isString($token)) {
				$this->emit('literal', $this->string($token));
			} elseif (preg_match('/^[0-9]/', $token) === 1) {
				$value = json_decode($token, true, 4, JSON_THROW_ON_ERROR);
				if (is_float($value) && !is_finite($value)) {
					$this->error('Non-finite number');
				}
				$this->emit('literal', $value);
			} elseif (in_array($token, ['true', 'false', 'null'], true)) {
				$this->emit('literal', match ($token) {'true' => true, 'false' => false, default => null});
			} else {
				$this->emit('load', $this->lookup($token));
			}
		}
		while (true) {
			if ($this->accept('.')) {
				$name = $this->identifier();
				if ($this->accept('(')) {
					if ($name === 'join') {
						if ($this->peek() === ')') {
							$this->emit('literal', ',');
						} else {
							$this->expression();
						}
						$this->expect(')');
						$this->emit('join');
					} elseif (in_array($name, ['find', 'map', 'filter', 'some', 'every', 'includes'], true)) {
						$this->arrayExpression($name);
					} elseif ($name === 'sort') {
						$this->error('sort() requires a local array variable and no comparator; use let items = expression; items.sort();');
					} else {
						$this->error('Unsupported method ' . $name . '(); use documented array methods or a for-of loop');
					}
				} else {
					$this->emit('literal', $name);
					$this->emit('get');
				}
			} elseif ($this->accept('[')) {
				$this->expression();
				$this->expect(']');
				$this->emit('get');
			} else {
				break;
			}
		}
	}

	/** Lower pure array operations to the existing budgeted loop instructions. */
	private function arrayExpression(string $method): void {
		$sequence = $this->slot();
		$cursor = $this->slot();
		$result = $this->slot();
		$this->emit('iter', $sequence, $cursor, $method);
		$needle = null;
		$this->scopes[] = [];
		if ($method === 'includes') {
			$variable = $this->slot();
			$this->expression();
			$needle = $this->slot();
			$this->emit('scalar');
			$this->emit('store', $needle);
		} else {
			$parenthesized = $this->accept('(');
			$name = $this->identifier();
			$variable = $this->declare($name);
			$index = null;
			if ($parenthesized && $this->accept(',')) { $index = $this->declare($this->identifier()); }
			if ($parenthesized) { $this->expect(')'); }
			$this->expect('=>');
			if ($this->peek() === '{') { $this->error('Array callbacks require one expression; wrap an object result in parentheses or use a for-of loop'); }
		}
		$this->emit('literal', match ($method) {'map', 'filter' => [], 'find' => null, 'every' => true, default => false});
		$this->emit('store', $result);
		$start = count($this->code);
		$next = $this->emit('next', 0, $sequence, $cursor, $variable);
		if ($method === 'includes') {
			$this->emit('load', $variable);
			$this->emit('load', $needle);
			$this->emit('binary', '===');
		} else {
			if ($index !== null) {
				$this->emit('load', $cursor);
				$this->emit('literal', 1);
				$this->emit('binary', '-');
				$this->emit('store', $index);
			}
			$this->callbackDepth++;
			try { $this->expression(); } finally { $this->callbackDepth--; }
		}
		$this->expect(')');
		$done = null;
		if ($method === 'map') {
			$this->emit('append', $result);
		} else {
			if ($method === 'every') { $this->emit('unary', '!'); }
			$skip = $this->emit('branch', 0);
			if ($method === 'filter' || $method === 'find') { $this->emit('load', $variable); }
			else { $this->emit('literal', $method !== 'every'); }
			$this->emit($method === 'filter' ? 'append' : 'store', $result);
			if ($method !== 'filter') { $done = $this->emit('jump', 0); }
			$this->patch($skip, count($this->code));
		}
		$this->emit('jump', $start);
		$end = count($this->code);
		$this->patch($next, $end);
		if ($done !== null) { $this->patch($done, $end); }
		$this->emit('load', $result);
		foreach ([$sequence, $cursor, $result] as $slot) { $this->emit('unset', $slot); }
		if ($needle !== null) { $this->emit('unset', $needle); $this->emit('unset', $variable); }
		$this->leaveScope();
	}

	private function isString(string $token): bool {
		return str_starts_with($token, '"') || str_starts_with($token, "'");
	}

	private function string(string $token): string {
		if (!$this->isString($token)) {
			$this->error('Expected a literal string');
		}
		if ($token[0] === "'") {
			$inner = substr($token, 1, -1);
			$inner = preg_replace_callback('/\\\\.|"/s', static function(array $match): string {
				return match ($match[0]) {"\\'" => "'", '"' => '\\"', default => $match[0]};
			}, $inner);
			$token = '"' . $inner . '"';
		}
		return json_decode($token, true, 4, JSON_THROW_ON_ERROR);
	}

	private function identifier(): string {
		$token = $this->take();
		if (preg_match('/^[A-Za-z_$][A-Za-z0-9_$]*$/D', $token) !== 1) {
			$this->error('Expected an identifier');
		}
		return $token;
	}

	private function declare(string $name): string {
		if (in_array($name, ['tools', 'let', 'if', 'else', 'for', 'of', 'return', 'break', 'continue', 'true', 'false', 'null'], true)) {
			$this->error('Reserved variable name: ' . $name);
		}
		$scope = array_key_last($this->scopes);
		if (isset($this->scopes[$scope][$name])) {
			$this->error('Variable already declared: ' . $name);
		}
		return $this->scopes[$scope][$name] = $this->slot();
	}

	private function lookup(string $name): string {
		for ($i = count($this->scopes) - 1; $i >= 0; $i--) {
			if (isset($this->scopes[$i][$name])) {
				return $this->scopes[$i][$name];
			}
		}
		$this->error('Unknown variable or unsupported expression: ' . $name);
	}

	private function slot(): string { return 'v' . $this->nextSlot++; }

	private function enter(): void {
		if (++$this->depth > ToolProgram::MAX_DEPTH) {
			$this->error('Program nesting limit exceeded');
		}
	}

	private function emit(string $op, mixed ...$arguments): int {
		if (count($this->code) >= ToolProgram::MAX_STEPS) {
			$this->error('Compiled program is too large');
		}
		$this->code[] = array_merge([$op], $arguments);
		return array_key_last($this->code);
	}

	private function patch(int $index, int $target): void { $this->code[$index][1] = $target; }
	private function peek(int $offset = 0): string { return $this->tokens[$this->position + $offset][0] ?? ''; }
	private function take(): string {
		if ($this->peek() === '') {
			$this->error('Unexpected end of program');
		}
		return $this->tokens[$this->position++][0];
	}
	private function accept(string $token): bool {
		if ($this->peek() !== $token) { return false; }
		$this->position++;
		return true;
	}
	private function expect(string $token): void {
		if ($this->accept($token)) { return; }
		if ($this->peek() === '=>') { $this->error('Arrow functions are only supported as expression callbacks in find, map, filter, some and every; otherwise use a for-of loop'); }
		if (in_array($this->peek(), ['==', '!='], true)) { $this->error('Use strict equality === or !=='); }
		if ($token === '=>') { $this->error('Expected an expression callback such as item => item.id'); }
		if ($token === ';') { $this->error('Missing statement semicolon; terminate statements with ; and finish with an explicit return value'); }
		$this->error('Expected ' . $token);
	}
	private function error(string $message): never {
		$this->errorAt($message, $this->tokens[$this->position][1] ?? strlen($this->source));
	}
	private function errorAt(string $message, int $offset): never {
		$before = substr($this->source, 0, $offset);
		$line = substr_count($before, "\n") + 1;
		$lastBreak = strrpos($before, "\n");
		$column = $lastBreak === false ? $offset + 1 : $offset - $lastBreak;
		throw new \InvalidArgumentException($message . ' at line ' . $line . ', byte column ' . $column . ' (byte ' . $offset . ').');
	}
}
