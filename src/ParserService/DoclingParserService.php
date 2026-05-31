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

namespace MissionBay\ParserService;

final class DoclingParserService extends AbstractParserService {

	public static function getName(): string {
		return 'doclingparserservice';
	}

	protected function getParserName(): string {
		return 'docling';
	}

	protected function callParserFile(string $filePath, string $filename): array {
		$fieldName = $this->getStringOption('file_field', 'file');
		$cfile = new \CURLFile($filePath, 'application/octet-stream', $filename);

		return $this->callMultipartEndpoint(
			[
				$fieldName => $cfile
			],
			$this->buildHeaders('X-Proxy-Token')
		);
	}

	protected function responseToText(array $response): string {
		$elements = $response['elements'] ?? null;

		if(!is_array($elements)) {
			return '';
		}

		$out = [];

		foreach($elements as $element) {
			if(!is_array($element) && !is_object($element)) {
				continue;
			}

			$data = (array)$element;
			$raw = $data['text'] ?? null;

			if(!is_string($raw)) {
				continue;
			}

			$text = trim($this->extractDoclingTextField($raw));

			if($text !== '') {
				$out[] = $text;
			}
		}

		return implode("\n\n", $out);
	}

	private function extractDoclingTextField(string $raw): string {
		$raw = trim($raw);

		if($raw === '') {
			return '';
		}

		if(preg_match("/\btext='([^']*)'/u", $raw, $match) === 1) {
			return (string)$match[1];
		}

		if(preg_match('/\btext="([^"]*)"/u', $raw, $match) === 1) {
			return (string)$match[1];
		}

		if(preg_match("/\borig='([^']*)'/u", $raw, $match) === 1) {
			return (string)$match[1];
		}

		if(preg_match('/\borig="([^"]*)"/u', $raw, $match) === 1) {
			return (string)$match[1];
		}

		return $raw;
	}
}
