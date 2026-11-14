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

namespace Base3\MissionBay\Mcp;

use Base3\AssistantFoundation\Api\IAgentContext;
use Base3\CoreFoundation\Logger\Api\ILogger;
use Base3\MissionBay\Api\IAgentComponentPresetMaterializer;
use Base3\MissionBay\Api\IAgentPromptProvider;
use Base3\MissionBay\Api\IAgentResourceProvider;
use Base3\MissionBay\Api\IAgentTool;

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
		return $this->materializeCapabilities($profile, $context)['tools'];
	}

	/**
	 * Materializes every MCP primitive exposed by the configured presets.
	 *
	 * Tool execution continues through the configured tool wrapper returned by
	 * the canonical preset materializer. Resource and prompt providers are read
	 * from the underlying preset resource because they are not executable tools.
	 *
	 * @param array<string,mixed> $profile
	 * @return array{
	 *     tools:array<int,IAgentTool>,
	 *     resourceProviders:array<int,IAgentResourceProvider>,
	 *     promptProviders:array<int,IAgentPromptProvider>
	 * }
	 */
	public function materializeCapabilities(array $profile, IAgentContext $context): array {
		$this->warnings = [];
		$tools = [];
		$resourceProviders = [];
		$promptProviders = [];

		foreach($this->normalizeStringList($profile['tools'] ?? []) as $presetId) {
			$materialization = $this->presetMaterializer->materialize($presetId, $context);

			foreach($materialization->getWarnings() as $warning) {
				$this->warn($warning, ['preset' => $presetId]);
			}

			$tool = $materialization->getTool();
			if($tool instanceof IAgentTool) {
				$this->addObject($tools, $tool);
			}
			else {
				$this->warn('MCP profile references a preset without an effective tool capability.', [
					'preset' => $presetId
				]);
			}

			$resource = $materialization->getResource();
			if($resource instanceof IAgentResourceProvider) {
				$this->addObject($resourceProviders, $resource);
			}
			if($resource instanceof IAgentPromptProvider) {
				$this->addObject($promptProviders, $resource);
			}
		}

		return [
			'tools' => array_values($tools),
			'resourceProviders' => array_values($resourceProviders),
			'promptProviders' => array_values($promptProviders)
		];
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

	private function addObject(array &$target, object $object): void {
		$target['object:' . spl_object_id($object)] = $object;
	}

	/** @param array<string,mixed> $context */
	private function warn(string $message, array $context = []): void {
		$this->warnings[] = $message;
		$context['scope'] = self::LOG_SCOPE;
		$this->logger->logLevel(ILogger::WARNING, $message, $context);
	}
}
