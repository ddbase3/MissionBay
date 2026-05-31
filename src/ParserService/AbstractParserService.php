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

use MissionBay\Api\IParserService;
use MissionBay\Dto\AgentContentItem;
use MissionBay\Dto\AgentParsedContent;
use RuntimeException;

abstract class AbstractParserService implements IParserService {

	private const DEFAULT_CONTENT_TYPE = 'application/x-agent-content-json';

	/**
	 * @var array<string,mixed>
	 */
	protected array $options = [];

	abstract public static function getName(): string;

	abstract protected function getParserName(): string;

	abstract protected function callParserFile(string $filePath, string $filename): array;

	abstract protected function responseToText(array $response): string;

	public function setOptions(array $options): void {
		$this->options = array_merge($this->options, $options);
	}

	public function getOptions(): array {
		return $this->options;
	}

	public function getPriority(): int {
		return $this->getIntOption('priority', 50);
	}

	public function supports(AgentContentItem $item): bool {
		return $this->detectInput($item) !== null;
	}

	public function parse(AgentContentItem $item): AgentParsedContent {
		$input = $this->detectInput($item);

		if($input === null) {
			throw new RuntimeException($this->getParserName() . ' parser: unsupported content item.');
		}

		$type = (string)$input['type'];

		if($type === 'text') {
			return $this->parseText($item, $input);
		}

		if($type === 'file') {
			return $this->parseFile($item, $input);
		}

		if($type === 'stream') {
			return $this->parseStream($item, $input);
		}

		throw new RuntimeException($this->getParserName() . " parser: unsupported input type '{$type}'.");
	}

	/**
	 * @return array<string,mixed>|null
	 */
	protected function detectInput(AgentContentItem $item): ?array {
		$contentType = (string)($item->contentType ?? '');

		if($contentType === $this->getContentType()) {
			$payload = $this->extractPayload($item);

			if($payload === null) {
				return null;
			}

			$type = strtolower(trim((string)($payload['type'] ?? '')));

			if($type === '' || !$this->isSupportedType($type)) {
				return null;
			}

			return [
				'type' => $type,
				'root' => is_array($item->content) ? $item->content : [],
				'payload' => $payload
			];
		}

		if(is_string($item->content) && $this->isSupportedType('text')) {
			return [
				'type' => 'text',
				'root' => [
					'content' => [
						'type' => 'text',
						'content' => $item->content
					]
				],
				'payload' => [
					'type' => 'text',
					'content' => $item->content
				]
			];
		}

		if(is_resource($item->content) && $this->isSupportedType('stream')) {
			return [
				'type' => 'stream',
				'root' => [
					'content' => [
						'type' => 'stream'
					]
				],
				'payload' => [
					'type' => 'stream',
					'stream' => $item->content
				]
			];
		}

		return null;
	}

	/**
	 * @param array<string,mixed> $input
	 */
	protected function parseText(AgentContentItem $item, array $input): AgentParsedContent {
		$root = is_array($input['root'] ?? null) ? $input['root'] : [];
		$payload = is_array($input['payload'] ?? null) ? $input['payload'] : [];

		$title = trim((string)($payload['title'] ?? ''));
		$text = trim((string)($payload['content'] ?? ''));

		if($title !== '' && $text !== '') {
			$text = $title . "\n\n" . $text;
		}
		elseif($title !== '' && $text === '') {
			$text = $title;
		}

		$text = $this->normalizeText($text);

		$payload['content'] = $text;
		$root['content'] = $payload;

		$metadata = is_array($item->metadata) ? $item->metadata : [];
		$metadata['type'] = 'text';
		$metadata['parser'] = $this->getParserName();

		return new AgentParsedContent(
			text: $text,
			metadata: $metadata,
			structured: $root,
			attachments: []
		);
	}

	/**
	 * @param array<string,mixed> $input
	 */
	protected function parseFile(AgentContentItem $item, array $input): AgentParsedContent {
		$root = is_array($input['root'] ?? null) ? $input['root'] : [];
		$payload = is_array($input['payload'] ?? null) ? $input['payload'] : [];

		$title = trim((string)($payload['title'] ?? ''));
		$meta = $payload['meta'] ?? null;
		$metaArr = (is_array($meta) || is_object($meta)) ? (array)$meta : [];

		$fileName = trim((string)($metaArr['file_name'] ?? ''));
		$path = trim((string)($metaArr['location'] ?? $metaArr['file_path'] ?? ''));

		if($path === '') {
			throw new RuntimeException($this->getParserName() . ' parser: missing meta.location or meta.file_path.');
		}

		$this->assertReadableFile($path);

		$maxBytes = $this->getIntOption('max_bytes', 0);

		if($maxBytes > 0) {
			$size = @filesize($path);

			if(is_int($size) && $size > $maxBytes) {
				throw new RuntimeException($this->getParserName() . ' parser: file exceeds max_bytes (' . $size . ' > ' . $maxBytes . ').');
			}
		}

		$effectiveName = $fileName !== '' ? $fileName : basename($path);
		$response = $this->callParserFile($path, $effectiveName);
		$text = $this->normalizeText($this->responseToText($response));

		if($title !== '' && $text !== '') {
			$text = $title . "\n\n" . $text;
		}
		elseif($title !== '' && $text === '') {
			$text = $title;
		}

		$payload['content'] = $text;
		$payload['meta'] = $metaArr;
		$root['content'] = $payload;

		$metadata = is_array($item->metadata) ? $item->metadata : [];
		$metadata['type'] = 'file';
		$metadata['parser'] = $this->getParserName();

		if($fileName !== '') {
			$metadata['file_name'] = $fileName;
		}

		$metadata['location'] = $path;

		return new AgentParsedContent(
			text: trim($text),
			metadata: $metadata,
			structured: $root,
			attachments: []
		);
	}

	/**
	 * @param array<string,mixed> $input
	 */
	protected function parseStream(AgentContentItem $item, array $input): AgentParsedContent {
		$payload = is_array($input['payload'] ?? null) ? $input['payload'] : [];
		$stream = $payload['stream'] ?? $item->content;

		if(!is_resource($stream)) {
			throw new RuntimeException($this->getParserName() . ' parser: stream input is not a resource.');
		}

		$tmp = tempnam(sys_get_temp_dir(), 'parser_stream_');

		if(!is_string($tmp) || $tmp === '') {
			throw new RuntimeException($this->getParserName() . ' parser: failed to create temp file.');
		}

		$out = fopen($tmp, 'wb');

		if(!is_resource($out)) {
			@unlink($tmp);
			throw new RuntimeException($this->getParserName() . ' parser: failed to open temp file.');
		}

		stream_copy_to_stream($stream, $out);
		fclose($out);

		try {
			$itemForFile = new AgentContentItem(
				content: [
					'content' => [
						'type' => 'file',
						'title' => (string)($payload['title'] ?? ''),
						'meta' => [
							'file_name' => (string)($payload['file_name'] ?? 'stream.bin'),
							'location' => $tmp
						]
					]
				],
				contentType: $this->getContentType(),
				metadata: is_array($item->metadata) ? $item->metadata : []
			);

			return $this->parseFile($itemForFile, [
				'type' => 'file',
				'root' => $itemForFile->content,
				'payload' => $itemForFile->content['content']
			]);
		}
		finally {
			@unlink($tmp);
		}
	}

	protected function assertReadableFile(string $path): void {
		if(!file_exists($path)) {
			throw new RuntimeException($this->getParserName() . " parser: file not found at path '{$path}'.");
		}

		if(!is_readable($path)) {
			throw new RuntimeException($this->getParserName() . " parser: file not readable at path '{$path}'.");
		}
	}

	/**
	 * @return array<string,mixed>|null
	 */
	protected function extractPayload(AgentContentItem $item): ?array {
		if(!is_array($item->content)) {
			return null;
		}

		$payload = $item->content['content'] ?? null;

		if(is_array($payload)) {
			return $payload;
		}

		if(is_object($payload)) {
			return (array)$payload;
		}

		return null;
	}

	protected function getContentType(): string {
		$contentType = trim((string)($this->options['content_type'] ?? ''));

		return $contentType !== '' ? $contentType : self::DEFAULT_CONTENT_TYPE;
	}

	protected function isSupportedType(string $type): bool {
		$type = strtolower(trim($type));

		if($type === '') {
			return false;
		}

		return in_array($type, $this->getSupportedTypes(), true);
	}

	/**
	 * @return array<int,string>
	 */
	protected function getSupportedTypes(): array {
		$value = $this->options['supported_types'] ?? ['file'];

		if(is_string($value)) {
			$value = preg_split('/[\r\n,]+/', $value) ?: [];
		}

		if(!is_array($value)) {
			return ['file'];
		}

		$out = [];

		foreach($value as $item) {
			$item = strtolower(trim((string)$item));

			if($item !== '') {
				$out[] = $item;
			}
		}

		$out = array_values(array_unique($out));

		return $out !== [] ? $out : ['file'];
	}

	protected function getStringOption(string $key, string $default): string {
		$value = trim((string)($this->options[$key] ?? ''));

		return $value !== '' ? $value : $default;
	}

	protected function getIntOption(string $key, int $default): int {
		$value = $this->options[$key] ?? null;

		if($value === null || $value === '' || !is_numeric($value)) {
			return $default;
		}

		$value = (int)$value;

		return $value >= 0 ? $value : $default;
	}

	protected function getRequiredBaseUrl(): string {
		$baseUrl = trim((string)($this->options['base_url'] ?? ''));

		if($baseUrl === '') {
			throw new RuntimeException($this->getParserName() . ' parser: missing base URL config.');
		}

		return $baseUrl;
	}

	protected function getAuthSecret(): string {
		return trim((string)($this->options['auth_secret'] ?? ''));
	}

	/**
	 * @return array<int,string>
	 */
	protected function buildHeaders(string $defaultAuthHeaderName): array {
		$authHeaderName = $this->getStringOption('auth_header_name', $defaultAuthHeaderName);
		$authSecret = $this->getAuthSecret();
		$headers = [];

		if($authHeaderName !== '') {
			if($authSecret === '') {
				throw new RuntimeException($this->getParserName() . ' parser: missing connection auth secret.');
			}

			$headers[] = $authHeaderName . ': ' . $authSecret;
		}

		$extraHeaders = $this->options['headers'] ?? [];

		if(is_array($extraHeaders)) {
			foreach($extraHeaders as $header) {
				if(is_string($header) && trim($header) !== '') {
					$headers[] = trim($header);
				}
			}
		}

		return $headers;
	}

	/**
	 * @param array<string,mixed> $postFields
	 * @param array<int,string> $headers
	 * @return array<string,mixed>
	 */
	protected function callMultipartEndpoint(array $postFields, array $headers): array {
		$endpoint = $this->getRequiredBaseUrl();
		$timeout = max(1, $this->getIntOption('timeout_seconds', 90));
		$connectTimeout = max(1, $this->getIntOption('connect_timeout_seconds', min(20, $timeout)));

		$ch = curl_init($endpoint);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $connectTimeout);

		if($headers !== []) {
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		}

		curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);

		$result = curl_exec($ch);

		if(curl_errno($ch)) {
			$error = curl_error($ch);
			curl_close($ch);
			throw new RuntimeException($this->getParserName() . ' parser request failed: ' . $error);
		}

		$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if($httpCode < 200 || $httpCode >= 300) {
			$body = substr((string)$result, 0, 600);
			$detail = $this->tryExtractErrorDetail($body);
			$message = $this->getParserName() . " parser failed with HTTP {$httpCode}";

			if($detail !== '') {
				$message .= ': ' . $detail;
			}
			elseif($body !== '') {
				$message .= ': ' . $body;
			}

			throw new RuntimeException($message);
		}

		$data = json_decode((string)$result, true);

		if(!is_array($data)) {
			throw new RuntimeException($this->getParserName() . ' parser response is not valid JSON.');
		}

		return $data;
	}

	protected function tryExtractErrorDetail(string $body): string {
		$decoded = json_decode($body, true);

		if(!is_array($decoded)) {
			return '';
		}

		$detail = $decoded['detail'] ?? ($decoded['error'] ?? null);

		return is_string($detail) ? trim($detail) : '';
	}

	protected function normalizeText(string $text): string {
		$text = str_replace(["\r\n", "\r"], "\n", $text);
		$lines = explode("\n", $text);

		foreach($lines as &$line) {
			$line = $this->normalizeInlineWhitespace($line);
		}

		unset($line);

		$text = implode("\n", $lines);
		$text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

		return trim($text);
	}

	protected function normalizeInlineWhitespace(string $text): string {
		$text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;

		return trim($text);
	}
}
