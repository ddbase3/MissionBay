<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 **********************************************************************/

namespace MissionBay\Mcp;

use Base3\Logger\Api\ILogger;
use MissionBay\Api\IAgentContext;
use MissionBay\Api\IAgentResourceProvider;

/**
 * McpResourceCatalog
 *
 * Aggregates agent resources from the active profile and materialized tools.
 */
class McpResourceCatalog {

	private const LOG_SCOPE = 'missionbay_mcp';
	private const PAGE_SIZE = 50;

	/**
	 * @param IAgentResourceProvider[] $providers
	 */
	public function __construct(
		private readonly array $providers,
		private readonly IAgentContext $context,
		private readonly ILogger $logger
	) {}

	public static function getName(): string {
		return 'mcpresourcecatalog';
	}

	/**
	 * @return array<string,mixed>
	 */
	public function listResources(?string $cursor = null): array {
		$resources = $this->collectResources();
		$offset = $this->decodeCursor($cursor);
		$page = array_slice($resources, $offset, self::PAGE_SIZE);
		$result = [
			'resources' => $page
		];

		$nextOffset = $offset + self::PAGE_SIZE;

		if($nextOffset < count($resources)) {
			$result['nextCursor'] = (string)$nextOffset;
		}

		return $result;
	}

	/**
	 * @return array<string,mixed>
	 */
	public function listResourceTemplates(?string $cursor = null): array {
		$templates = $this->collectResourceTemplates();
		$offset = $this->decodeCursor($cursor);
		$page = array_slice($templates, $offset, self::PAGE_SIZE);
		$result = [
			'resourceTemplates' => $page
		];

		$nextOffset = $offset + self::PAGE_SIZE;

		if($nextOffset < count($templates)) {
			$result['nextCursor'] = (string)$nextOffset;
		}

		return $result;
	}

	/**
	 * @return array<string,mixed>
	 */
	public function readResource(string $uri): array {
		$uri = trim($uri);

		if($uri === '') {
			throw new \InvalidArgumentException('Missing resources/read parameter: uri.');
		}

		foreach($this->providers as $provider) {
			if(!$provider instanceof IAgentResourceProvider) {
				continue;
			}

			$result = $provider->readResource($uri, $this->context);

			if(is_array($result)) {
				return $this->normalizeReadResult($result, $uri);
			}
		}

		throw new \InvalidArgumentException('Unknown MCP resource: ' . $uri);
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	private function collectResources(): array {
		$resources = [];
		$seen = [];

		foreach($this->providers as $provider) {
			if(!$provider instanceof IAgentResourceProvider) {
				continue;
			}

			try {
				foreach($provider->getResourceDefinitions($this->context) as $resource) {
					if(!is_array($resource)) {
						continue;
					}

					$resource = $this->normalizeResource($resource);
					$uri = (string)($resource['uri'] ?? '');

					if($uri === '' || isset($seen[$uri])) {
						continue;
					}

					$seen[$uri] = true;
					$resources[] = $resource;
				}
			}
			catch(\Throwable $e) {
				$this->logger->logLevel(ILogger::WARNING, 'MCP resource provider failed while listing resources.', [
					'scope' => self::LOG_SCOPE,
					'provider' => $provider::getName(),
					'error' => $e->getMessage()
				]);
			}
		}

		return $resources;
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	private function collectResourceTemplates(): array {
		$templates = [];
		$seen = [];

		foreach($this->providers as $provider) {
			if(!$provider instanceof IAgentResourceProvider) {
				continue;
			}

			try {
				foreach($provider->getResourceDefinitions($this->context) as $resource) {
					if(!is_array($resource)) {
						continue;
					}

					$template = $this->normalizeResourceTemplate($resource);
					$uriTemplate = (string)($template['uriTemplate'] ?? '');

					if($uriTemplate === '' || isset($seen[$uriTemplate])) {
						continue;
					}

					$seen[$uriTemplate] = true;
					$templates[] = $template;
				}
			}
			catch(\Throwable $e) {
				$this->logger->logLevel(ILogger::WARNING, 'MCP resource provider failed while listing resource templates.', [
					'scope' => self::LOG_SCOPE,
					'provider' => $provider::getName(),
					'error' => $e->getMessage()
				]);
			}
		}

		return $templates;
	}

	/**
	 * @param array<string,mixed> $resource
	 * @return array<string,mixed>
	 */
	private function normalizeResource(array $resource): array {
		$uri = trim((string)($resource['uri'] ?? ''));
		$name = trim((string)($resource['name'] ?? ''));
		$title = trim((string)($resource['title'] ?? ''));
		$description = trim((string)($resource['description'] ?? ''));
		$mimeType = trim((string)($resource['mimeType'] ?? 'application/json'));

		if($name === '') {
			$name = $this->resourceNameFromUri($uri);
		}

		if($title === '') {
			$title = $name !== '' ? $name : $uri;
		}

		$result = [
			'uri' => $uri,
			'name' => $name,
			'title' => $title,
			'mimeType' => $mimeType !== '' ? $mimeType : 'application/json'
		];

		if($description !== '') {
			$result['description'] = $description;
		}

		return $result;
	}

	/**
	 * @param array<string,mixed> $resource
	 * @return array<string,mixed>
	 */
	private function normalizeResourceTemplate(array $resource): array {
		$uriTemplate = trim((string)($resource['uriTemplate'] ?? ''));
		$name = trim((string)($resource['name'] ?? ''));
		$title = trim((string)($resource['title'] ?? ''));
		$description = trim((string)($resource['description'] ?? ''));
		$mimeType = trim((string)($resource['mimeType'] ?? 'application/json'));

		if($name === '') {
			$name = $this->resourceNameFromUri($uriTemplate);
		}

		if($title === '') {
			$title = $name !== '' ? $name : $uriTemplate;
		}

		$result = [
			'uriTemplate' => $uriTemplate,
			'name' => $name,
			'title' => $title,
			'mimeType' => $mimeType !== '' ? $mimeType : 'application/json'
		];

		if($description !== '') {
			$result['description'] = $description;
		}

		return $result;
	}

	/**
	 * @param array<string,mixed> $result
	 * @return array<string,mixed>
	 */
	private function normalizeReadResult(array $result, string $uri): array {
		$contents = $result['contents'] ?? [];

		if(!is_array($contents)) {
			$contents = [];
		}

		$out = [];

		foreach($contents as $content) {
			if(!is_array($content)) {
				continue;
			}

			$contentUri = trim((string)($content['uri'] ?? $uri));
			$mimeType = trim((string)($content['mimeType'] ?? 'application/json'));
			$text = (string)($content['text'] ?? '');

			$out[] = [
				'uri' => $contentUri !== '' ? $contentUri : $uri,
				'mimeType' => $mimeType !== '' ? $mimeType : 'application/json',
				'text' => $text
			];
		}

		if($out === []) {
			throw new \InvalidArgumentException('MCP resource provider returned no contents for: ' . $uri);
		}

		return [
			'contents' => $out
		];
	}

	private function decodeCursor(?string $cursor): int {
		if($cursor === null || trim($cursor) === '') {
			return 0;
		}

		if(!ctype_digit($cursor)) {
			throw new \InvalidArgumentException('Invalid resources/list cursor.');
		}

		return max(0, (int)$cursor);
	}

	private function resourceNameFromUri(string $uri): string {
		$name = strtolower($uri);
		$name = (string)preg_replace('/[^a-z0-9]+/', '-', $name);
		$name = trim($name, '-');

		return $name !== '' ? $name : 'resource';
	}
}
