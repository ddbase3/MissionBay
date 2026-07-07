<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 **********************************************************************/

namespace MissionBay\Mcp;

use MissionBay\Api\IAgentContext;
use MissionBay\Api\IAgentResourceProvider;

/**
 * McpProfileResourceProvider
 *
 * Exposes the active MCP profile as a read-only MCP resource. Sensitive values
 * such as bearer tokens are intentionally not exposed.
 */
class McpProfileResourceProvider implements IAgentResourceProvider {

	private const URI_PREFIX = 'missionbay://profile/';

	/**
	 * @param array<string,mixed> $profile
	 */
	public function __construct(private readonly array $profile) {}

	public static function getName(): string {
		return 'mcpprofileresourceprovider';
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public function getResourceDefinitions(IAgentContext $context): array {
		$id = $this->profileId();

		if($id === '') {
			return [];
		}

		return [[
			'uri' => self::URI_PREFIX . rawurlencode($id),
			'name' => 'missionbay-profile-' . $id,
			'title' => 'MissionBay MCP Profile: ' . $this->profileLabel(),
			'description' => $this->profileDescription(),
			'mimeType' => 'application/json'
		]];
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public function readResource(string $uri, IAgentContext $context): ?array {
		$id = $this->profileId();

		if($id === '' || $uri !== self::URI_PREFIX . rawurlencode($id)) {
			return null;
		}

		return [
			'contents' => [[
				'uri' => $uri,
				'mimeType' => 'application/json',
				'text' => $this->encode($this->safeProfile())
			]]
		];
	}

	/**
	 * @return array<string,mixed>
	 */
	private function safeProfile(): array {
		$tools = $this->profile['tools'] ?? [];

		if(!is_array($tools)) {
			$tools = [];
		}

		return [
			'id' => $this->profileId(),
			'label' => $this->profileLabel(),
			'description' => $this->profileDescription(),
			'type' => (string)($this->profile['type'] ?? 'mcp'),
			'enabled' => $this->isEnabled(),
			'token_configured' => trim((string)($this->profile['token'] ?? '')) !== '',
			'tools' => array_values(array_map('strval', $tools))
		];
	}

	private function profileId(): string {
		return trim((string)($this->profile['id'] ?? ''));
	}

	private function profileLabel(): string {
		$label = trim((string)($this->profile['label'] ?? ''));

		return $label !== '' ? $label : $this->profileId();
	}

	private function profileDescription(): string {
		$description = trim((string)($this->profile['description'] ?? ''));

		if($description !== '') {
			return $description;
		}

		return 'MissionBay MCP profile.';
	}

	private function isEnabled(): bool {
		$value = $this->profile['enabled'] ?? true;

		if(is_bool($value)) {
			return $value;
		}

		if(is_int($value)) {
			return $value !== 0;
		}

		$value = strtolower(trim((string)$value));

		return !in_array($value, ['0', 'false', 'no', 'off'], true);
	}

	private function encode(array $data): string {
		$json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

		return is_string($json) ? $json : '{}';
	}
}
