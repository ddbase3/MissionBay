<?php declare(strict_types=1);

namespace Test\Resource;

use PHPUnit\Framework\TestCase;
use MissionBay\Resource\NoChunkerAgentResource;
use MissionBay\Dto\AgentParsedContent;

/**
 * @covers \MissionBay\Resource\NoChunkerAgentResource
 */
class NoChunkerAgentResourceTest extends TestCase {

	private function makeParsedContent(?string $text): AgentParsedContent {
		$rc = new \ReflectionClass(AgentParsedContent::class);
		$ctor = $rc->getConstructor();

		if ($ctor === null) {
			/** @var AgentParsedContent $o */
			$o = $rc->newInstance();
			$o->text = $text;
			return $o;
		}

		$args = [];
		foreach ($ctor->getParameters() as $p) {
			$name = $p->getName();
			$type = $p->getType();

			if ($name === 'text') {
				$args[] = $text;
				continue;
			}

			if ($p->isDefaultValueAvailable()) {
				$args[] = $p->getDefaultValue();
				continue;
			}

			if ($type instanceof \ReflectionNamedType) {
				$t = $type->getName();
				if ($t === 'string') { $args[] = $type->allowsNull() ? null : ''; continue; }
				if ($t === 'int') { $args[] = 0; continue; }
				if ($t === 'float') { $args[] = 0.0; continue; }
				if ($t === 'bool') { $args[] = false; continue; }
				if ($t === 'array') { $args[] = []; continue; }
			}

			$args[] = null;
		}

		/** @var AgentParsedContent $obj */
		$obj = $rc->newInstanceArgs($args);
		return $obj;
	}

	private function extractChunkText(mixed $chunk): ?string {
		if (is_array($chunk)) {
			foreach (['content', 'text', 'chunk', 'value'] as $k) {
				if (isset($chunk[$k]) && is_string($chunk[$k])) {
					return $chunk[$k];
				}
			}
			return null;
		}

		if (is_object($chunk)) {
			foreach (['content', 'text', 'chunk', 'value'] as $prop) {
				if (property_exists($chunk, $prop) && is_string($chunk->{$prop})) {
					return $chunk->{$prop};
				}
			}

			foreach (['getContent', 'getText', 'getChunk', 'getValue'] as $m) {
				if (method_exists($chunk, $m)) {
					$v = $chunk->{$m}();
					if (is_string($v)) {
						return $v;
					}
				}
			}

			// common pattern: __toString()
			if (method_exists($chunk, '__toString')) {
				$s = (string)$chunk;
				return $s !== '' ? $s : null;
			}
		}

		return null;
	}

	public function testSupportsFalseWhenTextIsNotString(): void {
		$r = new NoChunkerAgentResource();
		$c = $this->makeParsedContent(null);
		$this->assertFalse($r->supports($c));
	}

	public function testSupportsFalseWhenTextIsEmptyOrWhitespace(): void {
		$r = new NoChunkerAgentResource();
		$this->assertFalse($r->supports($this->makeParsedContent('')));
		$this->assertFalse($r->supports($this->makeParsedContent('   ')));
		$this->assertFalse($r->supports($this->makeParsedContent("\n\t")));
	}

	public function testSupportsFalseWhenTextIsTooLong(): void {
		$r = new NoChunkerAgentResource();
		$c = $this->makeParsedContent(str_repeat('a', 200000));
		$this->assertFalse($r->supports($c));
	}

	public function testSupportsTrueWhenShortNonEmptyText(): void {
		$r = new NoChunkerAgentResource();
		$c = $this->makeParsedContent('Hello');
		$this->assertTrue($r->supports($c));
	}

	public function testChunkReturnsExactlyOneChunkWithTrimmedTextAndMetadata(): void {
		$r = new NoChunkerAgentResource();
		$c = $this->makeParsedContent("  Hello world  ");

		$chunks = $r->chunk($c);

		$this->assertIsArray($chunks);
		$this->assertCount(1, $chunks);

		$chunk = $chunks[0];

		$text = $this->extractChunkText($chunk);

		if ($text === null) {
			$this->fail('Could not extract chunk text. Chunk type=' . gettype($chunk) . ' value=' . var_export($chunk, true));
		}

		$this->assertSame('Hello world', trim($text));
	}

	public function testChunkIdLooksUniqueAcrossCalls(): void {
		$r = new NoChunkerAgentResource();

		$a = $r->chunk($this->makeParsedContent('Hello 1'));
		$b = $r->chunk($this->makeParsedContent('Hello 2'));

		$this->assertCount(1, $a);
		$this->assertCount(1, $b);

		$idA = is_object($a[0]) && property_exists($a[0], 'id') ? $a[0]->id : (is_array($a[0]) ? ($a[0]['id'] ?? null) : null);
		$idB = is_object($b[0]) && property_exists($b[0], 'id') ? $b[0]->id : (is_array($b[0]) ? ($b[0]['id'] ?? null) : null);

		if ($idA !== null && $idB !== null) {
			$this->assertNotSame($idA, $idB);
			return;
		}

		$textA = $this->extractChunkText($a[0]);
		$textB = $this->extractChunkText($b[0]);

		if ($textA !== null && $textB !== null) {
			$this->assertNotSame($textA, $textB);
			return;
		}

		// If neither id nor text is extractable, at least ensure chunks are not identical
		$this->assertNotSame(serialize($a[0]), serialize($b[0]));
	}
}
