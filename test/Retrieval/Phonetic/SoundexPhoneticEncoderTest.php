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

namespace Base3\MissionBay\Test\Retrieval\Phonetic;

use Base3\MissionBay\Retrieval\Phonetic\SoundexPhoneticEncoder;
use PHPUnit\Framework\TestCase;

final class SoundexPhoneticEncoderTest extends TestCase {

	public function testEmptyAndPunctuationOnlyInputsProduceEmptyCode(): void {
		$encoder = new SoundexPhoneticEncoder();
		foreach (['', '  ', '1234', '!?-'] as $token) {
			$this->assertSame('', $encoder->encode($token));
		}
	}

	public function testWhitespaceCaseAndGermanUmlautsNormalizeToSameCodes(): void {
		$encoder = new SoundexPhoneticEncoder();
		$this->assertSame($encoder->encode('Muller'), $encoder->encode('  Müller  '));
		$this->assertSame($encoder->encode('Strasse'), $encoder->encode('Straße'));
		$this->assertSame($encoder->encode('Robert'), $encoder->encode('ROBERT'));
	}

	public function testNumericTokensAreIgnoredButLettersRetainSoundex(): void {
		$encoder = new SoundexPhoneticEncoder();
		$this->assertSame(soundex('SMITH'), $encoder->encode('Smi123th!'));
		$this->assertSame(soundex('SMITH'), $encoder->encode('Smith'));
	}

	public function testAlgorithmIdentityIsStable(): void {
		$encoder = new SoundexPhoneticEncoder();
		$this->assertSame('soundexphoneticencoder', SoundexPhoneticEncoder::getName());
		$this->assertSame('soundex', $encoder->getAlgorithm());
		$this->assertSame('v1', $encoder->getVersion());
	}
}
