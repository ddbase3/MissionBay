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

namespace Base3\MissionBay\Test\Resource;

use Base3\AssistantFoundation\Api\IAiEmbeddingModel;
use Base3\AssistantFoundation\Api\IAiProvider;
use Base3\AssistantFoundation\Api\IServiceDriverDefinition;
use Base3\AssistantFoundation\Event\AiProviderRequestCompletedEvent;
use Base3\CoreFoundation\Api\IClassMap;
use Base3\Core\Event\EventManager;
use Base3\CoreFoundation\Settings\Api\ISettingsStore;
use Base3\MissionBay\Ai\AiProviderRequestEventDispatcher;
use Base3\MissionBay\Api\IAgentConfigValueResolver;
use Base3\MissionBay\EmbeddingModel\OpenAiEmbeddingModel;
use Base3\MissionBay\Resource\ConfiguredEmbeddingModelAgentResource;
use Base3\MissionBay\Service\ConfiguredServiceRuntimeResolver;
use Base3\MissionBay\ServiceDriver\OpenAiEmbeddingServiceDriverDefinition;
use Base3\MissionBay\Transport\OpenAiTransport;
use PHPUnit\Framework\TestCase;

final class ConfiguredEmbeddingUsageTestDouble2 implements ISettingsStore  {
			public function get(string $group, string $name, array $default = []): array {
				if($group === 'service-embedding' && $name === 'embedding-main') {
					return [
						'id' => 'embedding-main',
						'name' => 'Embedding Main',
						'serviceType' => 'embedding',
						'connection' => 'openai',
						'driver' => 'openai-embedding',
						'model' => 'text-embedding-3-small',
						'enabled' => true,
						'options' => []
					];
				}

				if($group === 'connection' && $name === 'openai') {
					return [
						'id' => 'openai',
						'name' => 'OpenAI',
						'type' => 'http',
						'driver' => 'http',
						'baseUrl' => 'https://api.openai.com',
						'auth' => [
							'type' => 'bearer',
							'secret' => [
								'mode' => 'env',
								'name' => 'OPENAI_API_KEY'
							]
						],
						'timeoutSeconds' => 60,
						'scope' => 'global',
						'enabled' => true,
						'options' => []
					];
				}

				return $default;
			}

			public function set(string $group, string $name, array $settings): void {}
			public function has(string $group, string $name): bool { return $this->get($group, $name, []) !== []; }
			public function remove(string $group, string $name): void {}
			public function getGroup(string $group): array { return []; }
			public function save(): void {}
			public function reload(): void {}
		}
