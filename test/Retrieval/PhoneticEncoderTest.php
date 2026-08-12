<?php declare(strict_types=1);

namespace MissionBay\Test\Retrieval;

use MissionBay\Retrieval\Phonetic\ColognePhoneticEncoder;
use MissionBay\Retrieval\Phonetic\SoundexPhoneticEncoder;
use PHPUnit\Framework\TestCase;

final class PhoneticEncoderTest extends TestCase {

	public function testSoundexUsesStandardEnglishEncoding(): void {
		$encoder = new SoundexPhoneticEncoder();

		$this->assertSame('A261', $encoder->encode('Ashcraft'));
		$this->assertSame($encoder->encode('Robert'), $encoder->encode('Rupert'));
	}

	public function testCologneTreatsCommonGermanNameVariantsEqually(): void {
		$encoder = new ColognePhoneticEncoder();

		$this->assertNotSame('', $encoder->encode('Meyer'));
		$this->assertSame($encoder->encode('Meyer'), $encoder->encode('Meier'));
		$this->assertSame($encoder->encode('Müller'), $encoder->encode('Mueller'));
	}
}
