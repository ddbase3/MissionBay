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

use Base3\MissionBay\Retrieval\Phonetic\ColognePhoneticEncoder;
use PHPUnit\Framework\TestCase;

final class ColognePhoneticEncoderTest extends TestCase {

	public function testIdentityAndEmptyInput(): void {
		$encoder = new ColognePhoneticEncoder();
		$this->assertSame('colognephoneticencoder', ColognePhoneticEncoder::getName());
		$this->assertSame('cologne', $encoder->getAlgorithm());
		$this->assertSame('v1', $encoder->getVersion());
		$this->assertSame('', $encoder->encode('  ---  '));
	}

	public function testEquivalentSpellingAndNormalization(): void {
		$encoder = new ColognePhoneticEncoder();
		$this->assertSame($encoder->encode('Müller'), $encoder->encode('Muller'));
		$this->assertSame($encoder->encode('Schmidt'), $encoder->encode('schmidt'));
		$this->assertSame($encoder->encode('Ärger'), $encoder->encode('Arger'));
		$this->assertSame($encoder->encode('Meyer'), $encoder->encode('Meyer!123'));
	}

	public function testConsonantContextAndAdjacentDuplicatesAffectEncoding(): void {
		$encoder = new ColognePhoneticEncoder();
		$this->assertSame('48', $encoder->encode('X'));
		$this->assertSame('48', $encoder->encode('KX'));
		$this->assertSame('48', $encoder->encode('CX'));
		$this->assertSame('1', $encoder->encode('PP'));
		$this->assertSame('0', $encoder->encode('AAAA'));
	}
}
