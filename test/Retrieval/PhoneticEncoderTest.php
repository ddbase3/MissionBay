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

use Base3\MissionBay\Retrieval\Phonetic\ColognePhoneticEncoder;
use Base3\MissionBay\Retrieval\Phonetic\SoundexPhoneticEncoder;
use PHPUnit\Framework\TestCase;

final class PhoneticEncoderTest extends TestCase {

	public function testSoundexUsesStandardEnglishEncoding(): void {
		$encoder = new SoundexPhoneticEncoder();

		$this->assertSame('A226', $encoder->encode('Ashcraft'));
		$this->assertSame($encoder->encode('Robert'), $encoder->encode('Rupert'));
	}

	public function testCologneTreatsCommonGermanNameVariantsEqually(): void {
		$encoder = new ColognePhoneticEncoder();

		$this->assertNotSame('', $encoder->encode('Meyer'));
		$this->assertSame($encoder->encode('Meyer'), $encoder->encode('Meier'));
		$this->assertSame($encoder->encode('Müller'), $encoder->encode('Mueller'));
	}
}
