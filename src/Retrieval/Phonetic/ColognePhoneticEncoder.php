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

final class ColognePhoneticEncoder implements IPhoneticEncoder {

	public static function getName(): string {
		return 'colognephoneticencoder';
	}

	public function getAlgorithm(): string {
		return 'cologne';
	}

	public function getVersion(): string {
		return 'v1';
	}

	public function encode(string $token): string {
		$word = $this->normalize($token);
		if($word === '') {
			return '';
		}

		$chars = str_split($word);
		$out = [];
		$previousCode = '';
		$count = count($chars);

		for($i = 0; $i < $count; $i++) {
			$current = $chars[$i];
			$next = $i + 1 < $count ? $chars[$i + 1] : '';
			$previous = $i > 0 ? $chars[$i - 1] : '';
			$code = $this->mapChar($current, $previous, $next, $i === 0);

			if($code === '') {
				continue;
			}

			foreach(str_split($code) as $digit) {
				if($digit === $previousCode) {
					continue;
				}

				$out[] = $digit;
				$previousCode = $digit;
			}
		}

		$filtered = [];
		foreach($out as $index => $digit) {
			if($digit === '0' && $index !== 0) {
				continue;
			}
			$filtered[] = $digit;
		}

		return implode('', $filtered);
	}

	private function normalize(string $word): string {
		$word = strtr(trim($word), [
			'ä' => 'a',
			'ö' => 'o',
			'ü' => 'u',
			'ß' => 's',
			'Ä' => 'A',
			'Ö' => 'O',
			'Ü' => 'U'
		]);
		$word = strtoupper($word);

		return preg_replace('/[^A-Z]/', '', $word) ?? '';
	}

	private function mapChar(string $current, string $previous, string $next, bool $isFirst): string {
		if(in_array($current, ['A', 'E', 'I', 'J', 'O', 'U', 'Y'], true)) {
			return '0';
		}
		if($current === 'H') {
			return '';
		}
		if($current === 'B') {
			return '1';
		}
		if($current === 'P') {
			return $next === 'H' ? '3' : '1';
		}
		if($current === 'D' || $current === 'T') {
			return in_array($next, ['C', 'S', 'Z'], true) ? '8' : '2';
		}
		if(in_array($current, ['F', 'V', 'W'], true)) {
			return '3';
		}
		if(in_array($current, ['G', 'K', 'Q'], true)) {
			return '4';
		}
		if($current === 'C') {
			if($isFirst) {
				return $this->isCFollowedByHard($next) ? '4' : '8';
			}
			if($previous === 'S' || $previous === 'Z') {
				return '8';
			}
			return $this->isCFollowedByHard($next) ? '4' : '8';
		}
		if($current === 'X') {
			return in_array($previous, ['C', 'K', 'Q'], true) ? '8' : '48';
		}
		if($current === 'L') {
			return '5';
		}
		if($current === 'M' || $current === 'N') {
			return '6';
		}
		if($current === 'R') {
			return '7';
		}
		if($current === 'S' || $current === 'Z') {
			return '8';
		}

		return '';
	}

	private function isCFollowedByHard(string $next): bool {
		return in_array($next, ['A', 'H', 'K', 'L', 'O', 'Q', 'R', 'U', 'X'], true);
	}
}
