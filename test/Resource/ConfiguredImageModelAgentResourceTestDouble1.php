<?php declare(strict_types=1);

namespace MissionBay\Test\Resource;

use AssistantFoundation\Api\IImageGenerationModel;
use AssistantFoundation\Api\IServiceDriverDefinition;
use Base3\Api\IClassMap;
use Base3\Settings\Api\ISettingsStore;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\ImageModel\MistralImageModel;
use MissionBay\Resource\ConfiguredImageModelAgentResource;
use MissionBay\Service\ConfiguredServiceRuntimeResolver;
use MissionBay\ServiceDriver\MistralImageServiceDriverDefinition;
use PHPUnit\Framework\TestCase;

final class ConfiguredImageModelAgentResourceTestDouble1 implements IAgentConfigValueResolver  {
			public function resolveValue(array|string|int|float|bool|null $config): mixed {
				if(is_array($config) && ($config['mode'] ?? null) === 'env') {
					return 'mistral-test-key';
				}

				return $config;
			}
		}
