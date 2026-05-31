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

final class UnstructuredParserService extends AbstractParserService {

	public static function getName(): string {
		return 'unstructuredparserservice';
	}

	protected function getParserName(): string {
		return 'unstructured';
	}

	protected function callParserFile(string $filePath, string $filename): array {
		$fieldName = $this->getStringOption('file_field', 'files');
		$cfile = new \CURLFile($filePath, 'application/octet-stream', $filename);

		return $this->callMultipartEndpoint(
			[
				$fieldName => $cfile
			],
			$this->buildHeaders('X-API-Key')
		);
	}

	protected function responseToText(array $response): string {
		$elements = $this->normalizeElements($response);
		$out = [];

		foreach($elements as $element) {
			if(!is_array($element) && !is_object($element)) {
				continue;
			}

			$data = (array)$element;
			$text = $data['text'] ?? null;

			if(!is_string($text)) {
				continue;
			}

			$text = trim($text);

			if($text !== '') {
				$out[] = $text;
			}
		}

		return implode("\n\n", $out);
	}

	/**
	 * @param array<string|int,mixed> $response
	 * @return array<int,mixed>
	 */
	private function normalizeElements(array $response): array {
		if(array_is_list($response)) {
			return $response;
		}

		$elements = $response['elements'] ?? null;

		return is_array($elements) ? $elements : [];
	}
}
