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

namespace MissionBay\Retrieval\Phonetic;

use AssistantFoundation\Api\IPhoneticEncoder;

final class SoundexPhoneticEncoder implements IPhoneticEncoder {

	public static function getName(): string {
		return 'soundexphoneticencoder';
	}

	public function getAlgorithm(): string {
		return 'soundex';
	}

	public function getVersion(): string {
		return 'v1';
	}

	public function encode(string $token): string {
		$token = $this->normalize($token);
		if($token === '') {
			return '';
		}

		return soundex($token);
	}

	private function normalize(string $token): string {
		$token = strtr(trim($token), [
			'ä' => 'a',
			'ö' => 'o',
			'ü' => 'u',
			'ß' => 's',
			'Ä' => 'A',
			'Ö' => 'O',
			'Ü' => 'U'
		]);
		$token = strtoupper($token);

		if(function_exists('iconv')) {
			$converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $token);
			if(is_string($converted) && $converted !== '') {
				$token = $converted;
			}
		}

		return preg_replace('/[^A-Z]/', '', $token) ?? '';
	}
}
