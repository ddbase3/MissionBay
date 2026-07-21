<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 **********************************************************************/

namespace MissionBay\Mcp;

use AssistantFoundation\Api\IAgentContext;
use Base3\Logger\Api\ILogger;
use MissionBay\Api\IAgentComponentPresetMaterializer;
use MissionBay\Api\IAgentTool;

/**
 * Materializes MCP tool profiles through the canonical component-preset materializer.
 */
final class McpToolPresetMaterializer {

	private const LOG_SCOPE = 'missionbay_mcp';

	/** @var array<int,string> */
	private array $warnings = [];

	public function __construct(
		private readonly IAgentComponentPresetMaterializer $presetMaterializer,
		private readonly ILogger $logger
	) {}

	public static function getName(): string {
		return 'mcptoolpresetmaterializer';
	}

	/** @param array<string,mixed> $profile */
	public function createContext(array $profile): IAgentContext {
		return $this->presetMaterializer->createContext([
			'mcp' => true,
			'mcp_profile_id' => (string)($profile['id'] ?? ''),
			'mcp_profile_label' => (string)($profile['label'] ?? '')
		]);
	}

	/**
	 * @param array<string,mixed> $profile
	 * @return IAgentTool[]
	 */
	public function materialize(array $profile, IAgentContext $context): array {
		$this->warnings = [];
		$tools = [];

		foreach($this->normalizeStringList($profile['tools'] ?? []) as $presetId) {
			$materialization = $this->presetMaterializer->materialize($presetId, $context);

			foreach($materialization->getWarnings() as $warning) {
				$this->warn($warning, ['preset' => $presetId]);
			}

			$tool = $materialization->getTool();
			if(!$tool instanceof IAgentTool) {
				$this->warn('MCP profile references a preset without an effective tool capability.', [
					'preset' => $presetId
				]);
				continue;
			}

			$tools[] = $tool;
		}

		return $tools;
	}

	/** @return array<int,string> */
	public function getWarnings(): array {
		return array_values(array_unique($this->warnings));
	}

	/** @return array<int,string> */
	private function normalizeStringList(mixed $value): array {
		if($value === null || $value === '') {
			return [];
		}

		if(is_string($value)) {
			$value = explode(',', $value);
		}

		if(!is_array($value)) {
			return [];
		}

		$result = [];
		foreach($value as $item) {
			$item = trim((string)$item);
			if($item !== '') {
				$result[] = $item;
			}
		}

		return array_values(array_unique($result));
	}

	/** @param array<string,mixed> $context */
	private function warn(string $message, array $context = []): void {
		$this->warnings[] = $message;
		$context['scope'] = self::LOG_SCOPE;
		$this->logger->logLevel(ILogger::WARNING, $message, $context);
	}
}
