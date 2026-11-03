<?php declare(strict_types=1);

namespace MissionBay\Test\Service;

use MissionBay\Api\IAgentComponentPresetRepository;
use MissionBay\Service\AgentComponentFlowBuilder;
use MissionBay\Service\AgentComponentPresetFlowExpander;
use PHPUnit\Framework\TestCase;

final class AgentComponentFlowBuilderDeduplicationTestDouble1 implements IAgentComponentPresetRepository  {
			private array $presets = [
				'user-prefs' => [
					'id' => 'user-prefs',
					'type' => 'userprefsagentresource',
					'enabled' => true,
					'capabilities' => ['tool', 'context']
				],
				'session-main' => [
					'id' => 'session-main',
					'type' => 'sessionmemoryagentresource',
					'enabled' => true,
					'capabilities' => ['memory']
				],
				'chat-primary' => [
					'id' => 'chat-primary',
					'type' => 'configuredchatmodelagentresource',
					'enabled' => true,
					'capabilities' => ['chatmodel'],
					'config' => ['service' => ['mode' => 'fixed', 'value' => 'llm-primary']]
				],
				'chat-secondary' => [
					'id' => 'chat-secondary',
					'type' => 'configuredchatmodelagentresource',
					'enabled' => true,
					'capabilities' => ['chatmodel'],
					'config' => ['service' => ['mode' => 'fixed', 'value' => 'llm-secondary']]
				],
				'chat-router' => [
					'id' => 'chat-router',
					'type' => 'routingchatmodelagentresource',
					'enabled' => true,
					'capabilities' => ['chatmodel'],
					'docks' => ['targets' => ['chat-primary', 'chat-secondary']]
				],
				'embedding-main' => [
					'id' => 'embedding-main',
					'type' => 'configuredembeddingmodelagentresource',
					'enabled' => true,
					'config' => ['service' => ['mode' => 'fixed', 'value' => 'embedding-service']]
				],
				'embedding-cache' => [
					'id' => 'embedding-cache',
					'type' => 'embeddingcacheagentresource',
					'enabled' => true,
					'docks' => ['embedding' => ['embedding-main']]
				],
				'vector-main' => [
					'id' => 'vector-main',
					'type' => 'configuredvectorstoreagentresource',
					'enabled' => true,
					'config' => ['service' => ['mode' => 'fixed', 'value' => 'vector-service']]
				],
				'filter-kind' => [
					'id' => 'filter-kind',
					'type' => 'testvectorfilter',
					'enabled' => true
				],
				'retrieval-main' => [
					'id' => 'retrieval-main',
					'type' => 'retrievalagenttool',
					'enabled' => true,
					'capabilities' => ['tool'],
					'docks' => [
						'embedding' => ['embedding-cache'],
						'vectorstore' => ['vector-main'],
						'filters' => ['filter-kind']
					]
				]
			];
			public function getPresets(): array { return $this->presets; }
			public function getPreset(string $id, array $default = []): array { return $this->presets[$id] ?? $default; }
			public function hasPreset(string $id): bool { return isset($this->presets[$id]); }
			public function savePreset(string $id, array $preset): void { $this->presets[$id] = $preset; }
			public function removePreset(string $id): void { unset($this->presets[$id]); }
		}
