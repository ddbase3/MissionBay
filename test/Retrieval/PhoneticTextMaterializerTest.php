<?php declare(strict_types=1);

namespace MissionBay\Test\Retrieval;

use AssistantFoundation\Api\IPhoneticEncoder;
use AssistantFoundation\Api\IRetrievalCollectionDefinition;
use Base3\Api\IClassMap;
use MissionBay\Retrieval\Phonetic\ColognePhoneticEncoder;
use MissionBay\Retrieval\Phonetic\SoundexPhoneticEncoder;
use MissionBay\Retrieval\PhoneticTextMaterializer;
use PHPUnit\Framework\TestCase;

final class PhoneticTextMaterializerTest extends TestCase {

	public function testMaterializerUsesSelectedEncodersAndSkipsNoise(): void {
		$cologne = new ColognePhoneticEncoder();
		$soundex = new SoundexPhoneticEncoder();
		$classMap = $this->createMock(IClassMap::class);
		$classMap->method('getInstanceByInterfaceName')->willReturnCallback(
			static function(string $interface, string $name) use ($cologne, $soundex): ?IPhoneticEncoder {
				if($interface !== IPhoneticEncoder::class) return null;
				return match($name) {
					ColognePhoneticEncoder::getName() => $cologne,
					SoundexPhoneticEncoder::getName() => $soundex,
					default => null
				};
			}
		);

		$definition = $this->createMock(IRetrievalCollectionDefinition::class);
		$definition->method('getPhoneticEncoderNames')->willReturnCallback(
			static fn(string $collectionKey, array $context = []): array => ($context['lang'] ?? '') === 'de'
				? [ColognePhoneticEncoder::getName()]
				: [ColognePhoneticEncoder::getName(), SoundexPhoneticEncoder::getName()]
		);

		$materializer = new PhoneticTextMaterializer($classMap, $definition);
		$german = $materializer->materialize(
			'test',
			'der Meyer und https://example.invalid/Schmidt test@example.invalid',
			['lang' => 'de']
		);
		$multilingual = $materializer->materialize('test', 'Meyer');

		$this->assertStringContainsString('phcolognev1x', $german);
		$this->assertStringNotContainsString('phsoundexv1x', $german);
		$this->assertSame(1, substr_count($german, 'phcolognev1x'));
		$this->assertStringContainsString('phcolognev1x', $multilingual);
		$this->assertStringContainsString('phsoundexv1x', $multilingual);
	}
}
