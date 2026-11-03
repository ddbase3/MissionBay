<?php declare(strict_types=1);

namespace MissionBay\Test\Retrieval\Phonetic;

use MissionBay\Retrieval\Phonetic\ColognePhoneticEncoder;
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
