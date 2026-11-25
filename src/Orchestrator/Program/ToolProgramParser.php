<?php declare(strict_types=1);

namespace Base3\MissionBay\Orchestrator\Program;

/** Compiles the documented restricted JavaScript grammar to bounded VM instructions. */
final class ToolProgramParser {

	private array $tokens = [];
	private int $position = 0;
	private array $code = [];
	private array $tools = [];
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
		$this->tokens = $this->tokenize($source);
		$this->position = 0;
		$this->code = [];
		$this->tools = [];
		$this->scopes = [[]];
		$this->loops = [];
		$this->nextSlot = 0;
		$this->depth = 0;
		while ($this->peek() !== '') {
			$this->statement();
		}
		$this->emit('halt');
		return new ToolProgram($this->code, array_keys($this->tools));
	}

	private function tokenize(string $source): array {
		$tokens = [];
		$offset = 0;
		$pattern = <<<'REGEX'
~\G(?:\s+|//[^\r\n]*|/\*[\s\S]*?\*/|"(?:[^"\\\x00-\x1f]|\\["\\/bfnrt]|\\u[0-9a-fA-F]{4})*"|'(?:[^'\\\x00-\x1f]|\\['"\\/bfnrt]|\\u[0-9a-fA-F]{4})*'|(?:0|[1-9][0-9]*)(?:\.[0-9]+)?(?:[eE][+-]?[0-9]+)?|[A-Za-z_$][A-Za-z0-9_$]*|===|!==|<=|>=|&&|\|\||[{}\[\]();:,.=+*/%<>!\-])~
REGEX;
		while ($offset < strlen($source)) {
			if (preg_match($pattern, $source, $match, 0, $offset) !== 1) {
				throw new \InvalidArgumentException('Unsupported program syntax at byte ' . $offset . '.');
			}
			$text = $match[0];
			if (!ctype_space($text[0]) && !str_starts_with($text, '//') && !str_starts_with($text, '/*')) {
				$tokens[] = [$text, $offset];
			}
			$offset += strlen($text);
		}
		return $tokens;
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
			$this->expect('of');
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
			$loop = array_pop($this->loops);
			foreach ($loop['breaks'] as $jump) {
				$this->patch($jump, $end);
			}
			$this->emit('unset', $sequence);
			$this->emit('unset', $cursor);
			$this->leaveScope();
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
			$this->expression();
			$this->expect(';');
			$this->emit('return');
			return;
		}
		if ($this->peek() === '{') {
			$this->block();
			return;
		}
		if ($this->peek(1) === '=') {
			$slot = $this->lookup($this->identifier());
			$this->expect('=');
			$this->expression();
			$this->emit('store', $slot);
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
					$this->emit('boolean');
					$this->patch($jump, count($this->code));
				} else {
					$this->expression($precedence + 1);
					$this->emit('binary', $operator);
				}
			}
		} finally {
			$this->depth--;
		}
	}

	private function prefix(): void {
		if ($this->accept('!') || $this->accept('-')) {
			$operator = $this->tokens[$this->position - 1][0];
			$this->expression(7);
			$this->emit('unary', $operator);
			return;
		}
		if ($this->accept('(')) {
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
		} elseif ($this->accept('tools')) {
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
				$this->emit('literal', $this->identifier());
				$this->emit('get');
			} elseif ($this->accept('[')) {
				$this->expression();
				$this->expect(']');
				$this->emit('get');
			} else {
				break;
			}
		}
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
		if (!$this->accept($token)) { $this->error('Expected ' . $token); }
	}
	private function error(string $message): never {
		throw new \InvalidArgumentException($message . ' at byte ' . ($this->tokens[$this->position][1] ?? 'end') . '.');
	}
}
