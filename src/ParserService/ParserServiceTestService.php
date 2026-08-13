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

use Base3\Logger\Api\ILogger;
use MissionBay\Api\IParserService;
use MissionBay\Api\IParserServiceTestService;
use MissionBay\Dto\AgentContentItem;
use RuntimeException;

final class ParserServiceTestService implements IParserServiceTestService {

	private const LOG_SCOPE = 'embedding';
	private const TEST_MARKER = 'BASE3 parser service test marker 4f6b21';
	private const PREVIEW_LENGTH = 2000;

	public function __construct(
		private readonly ILogger $logger
	) {}

	public function test(IParserService $service): array {
		$options = $service->getOptions();
		$serviceId = trim((string)($options['parser_id'] ?? ''));
		$driver = trim((string)($options['service_driver'] ?? ''));
		$connectionId = trim((string)($options['connection_id'] ?? ''));
		$inputType = $this->selectInputType($options);
		$startedAt = microtime(true);

		$this->logger->info('Parser service test started.', [
			'scope' => self::LOG_SCOPE,
			'parser_service_id' => $serviceId,
			'parser_driver' => $driver,
			'connection_id' => $connectionId,
			'input_type' => $inputType
		]);

		try {
			$item = $this->createTestItem($inputType, $options);

			if(!$service->supports($item)) {
				throw new RuntimeException('Configured parser service does not support its generated test input.');
			}

			$parsed = $service->parse($item);
			$text = trim((string)($parsed->text ?? ''));

			if($text === '') {
				throw new RuntimeException('Parser test returned no text.');
			}

			if(stripos($text, self::TEST_MARKER) === false) {
				throw new RuntimeException('Parser test returned text, but the test marker was not found in the parsed result.');
			}

			$durationMs = (int)round((microtime(true) - $startedAt) * 1000);
			$result = [
				'serviceId' => $serviceId,
				'driver' => $driver,
				'connectionId' => $connectionId,
				'inputType' => $inputType,
				'inputName' => $inputType === 'file' ? 'base3-parser-service-test.txt' : $inputType,
				'durationMs' => $durationMs,
				'textLength' => strlen($text),
				'preview' => $this->limitText($text, self::PREVIEW_LENGTH)
			];

			$this->logger->info('Parser service test succeeded.', [
				'scope' => self::LOG_SCOPE,
				'parser_service_id' => $serviceId,
				'parser_driver' => $driver,
				'connection_id' => $connectionId,
				'input_type' => $inputType,
				'duration_ms' => $durationMs,
				'text_length' => strlen($text)
			]);

			return $result;
		}
		catch(\Throwable $e) {
			$this->logger->error('Parser service test failed.', [
				'scope' => self::LOG_SCOPE,
				'parser_service_id' => $serviceId,
				'parser_driver' => $driver,
				'connection_id' => $connectionId,
				'input_type' => $inputType,
				'duration_ms' => (int)round((microtime(true) - $startedAt) * 1000),
				'error' => $e->getMessage()
			]);

			throw $e;
		}
	}

	/**
	 * @param array<string,mixed> $options
	 */
	private function selectInputType(array $options): string {
		$supported = $options['supported_types'] ?? ['file'];

		if(is_string($supported)) {
			$supported = preg_split('/[\r\n,]+/', $supported) ?: [];
		}

		if(!is_array($supported)) {
			$supported = ['file'];
		}

		$normalized = [];

		foreach($supported as $type) {
			$type = strtolower(trim((string)$type));
			if($type !== '') {
				$normalized[] = $type;
			}
		}

		foreach(['file', 'stream', 'text'] as $preferred) {
			if(in_array($preferred, $normalized, true)) {
				return $preferred;
			}
		}

		throw new RuntimeException('Parser test requires one supported input type: file, stream or text.');
	}

	/**
	 * @param array<string,mixed> $options
	 */
	private function createTestItem(string $inputType, array $options): AgentContentItem {
		$contentType = trim((string)($options['content_type'] ?? 'application/x-agent-content-json'));
		$text = self::TEST_MARKER . "\n\nThis mini document verifies that the configured parser connection and driver can receive a document and return parsed text.";

		if($inputType === 'text') {
			return new AgentContentItem(
				action: 'upsert',
				collectionKey: 'parser-service-test',
				id: 'parser-service-test',
				hash: hash('sha256', $text),
				contentType: 'text/plain',
				content: $text,
				isBinary: false,
				size: strlen($text),
				metadata: ['parser_service_test' => true]
			);
		}

		if($inputType === 'stream') {
			$stream = fopen('php://temp', 'w+b');

			if(!is_resource($stream)) {
				throw new RuntimeException('Unable to create parser test stream.');
			}

			fwrite($stream, $text);
			rewind($stream);

			return new AgentContentItem(
				action: 'upsert',
				collectionKey: 'parser-service-test',
				id: 'parser-service-test',
				hash: hash('sha256', $text),
				contentType: 'application/octet-stream',
				content: $stream,
				isBinary: true,
				size: strlen($text),
				metadata: ['parser_service_test' => true]
			);
		}

		$tmp = tempnam(sys_get_temp_dir(), 'base3_parser_test_');

		if(!is_string($tmp) || $tmp === '') {
			throw new RuntimeException('Unable to create parser test document.');
		}

		if(file_put_contents($tmp, $text) === false) {
			@unlink($tmp);
			throw new RuntimeException('Unable to write parser test document.');
		}

		register_shutdown_function(static function() use ($tmp): void {
			@unlink($tmp);
		});

		return new AgentContentItem(
			action: 'upsert',
			collectionKey: 'parser-service-test',
			id: 'parser-service-test',
			hash: hash('sha256', $text),
			contentType: $contentType,
			content: [
				'content' => [
					'type' => 'file',
					'content' => '',
					'meta' => [
						'file_name' => 'base3-parser-service-test.txt',
						'location' => $tmp,
						'file_path' => $tmp
					]
				]
			],
			isBinary: false,
			size: strlen($text),
			metadata: ['parser_service_test' => true]
		);
	}

	private function limitText(string $text, int $maxLength): string {
		if(strlen($text) <= $maxLength) {
			return $text;
		}

		return substr($text, 0, $maxLength) . "\n...";
	}
}
