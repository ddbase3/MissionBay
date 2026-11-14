<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 *
 * MissionBay extends the BASE3 framework with a modular runtime
 * foundation for agent flows, reusable nodes, and dockable resources.
 * It provides declarative execution for AI-driven workflows.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * https://base3.de/v/missionbay
 * https://github.com/ddbase3/MissionBay
 **********************************************************************/

namespace Base3\MissionBay\Test\Retrieval;

use Base3\AssistantFoundation\Api\IPhoneticEncoder;
use Base3\AssistantFoundation\Api\IRetrievalCollectionDefinition;
use Base3\CoreFoundation\Api\IClassMap;
use Base3\MissionBay\Retrieval\Phonetic\ColognePhoneticEncoder;
use Base3\MissionBay\Retrieval\Phonetic\SoundexPhoneticEncoder;
use Base3\MissionBay\Retrieval\PhoneticTextMaterializer;
use PHPUnit\Framework\TestCase;

final class PhoneticTextMaterializerTest extends TestCase {

	public function testMaterializerUsesSelectedEncodersAndSkipsNoise(): void {
		$cologne = new ColognePhoneticEncoder();
		$soundex = new SoundexPhoneticEncoder();
		$classMap = $this->createStub(IClassMap::class);
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

		$definition = $this->createStub(IRetrievalCollectionDefinition::class);
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
