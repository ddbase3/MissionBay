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

final class ConfiguredImageModelAgentResourceTest extends TestCase {

	public function testResolvesMistralDriverAndMapsSchemaOptionsToRuntimeKeys(): void {
		$settingsStore = $this->createMock(ISettingsStore::class);
		$settingsStore->method('get')->willReturnCallback(
			static function(string $group, string $name, array $default = []): array {
				if($group === 'service-image' && $name === 'mistral_course_images') {
					return [
						'id' => 'mistral_course_images',
						'name' => 'Mistral Course Images',
						'serviceType' => 'image',
						'connection' => 'mistral',
						'driver' => 'mistral-image',
						'model' => 'mistral-small-latest',
						'enabled' => true,
						'options' => [
							'toolChoice' => 'required',
							'timeoutSeconds' => 180,
							'connectTimeoutSeconds' => 25,
							'endpoint' => 'https://duplicate.example.test',
							'api_key' => 'duplicate-key',
							'authType' => 'none'
						]
					];
				}

				if($group === 'connection' && $name === 'mistral') {
					return [
						'id' => 'mistral',
						'name' => 'Mistral',
						'type' => 'http',
						'driver' => 'http',
						'baseUrl' => 'https://api.mistral.ai',
						'auth' => [
							'type' => 'bearer',
							'secret' => [
								'mode' => 'env',
								'name' => 'MISTRAL_API_KEY'
							]
						],
						'timeoutSeconds' => 120,
						'scope' => 'global',
						'enabled' => true,
						'options' => []
					];
				}

				return $default;
			}
		);

		$resolver = new class implements IAgentConfigValueResolver {
			public function resolveValue(array|string|int|float|bool|null $config): mixed {
				if(is_array($config) && ($config['mode'] ?? null) === 'env') {
					return 'mistral-test-key';
				}

				return $config;
			}
		};

		$capturedOptions = [];
		$imageModel = $this->createMock(IImageGenerationModel::class);
		$imageModel->expects($this->once())
			->method('setOptions')
			->willReturnCallback(static function(array $options) use (&$capturedOptions): void {
				$capturedOptions = $options;
			});

		$driverDefinition = new MistralImageServiceDriverDefinition();
		$classMap = $this->createMock(IClassMap::class);
		$classMap->method('getInstancesByInterface')
			->with(IServiceDriverDefinition::class)
			->willReturn([$driverDefinition]);
		$classMap->method('getClassByInterfaceName')
			->with(IImageGenerationModel::class, MistralImageModel::getName())
			->willReturn(MistralImageModel::class);
		$classMap->method('instantiate')
			->with(MistralImageModel::class)
			->willReturn($imageModel);

		$resource = new ConfiguredImageModelAgentResource(
			$resolver,
			$settingsStore,
			new ConfiguredServiceRuntimeResolver($settingsStore, $classMap, $resolver),
			'course-image'
		);
		$resource->setConfig([
			'service' => 'mistral_course_images'
		]);
		$resource->getOptions();

		$this->assertSame('mistral-small-latest', $capturedOptions['model']);
		$this->assertSame('https://api.mistral.ai', $capturedOptions['endpoint']);
		$this->assertSame('mistral-test-key', $capturedOptions['apikey']);
		$this->assertArrayNotHasKey('tool_choice', $capturedOptions);
		$this->assertSame(180, $capturedOptions['timeout_seconds']);
		$this->assertSame(25, $capturedOptions['connect_timeout_seconds']);
		$this->assertArrayNotHasKey('size', $capturedOptions);
	}
}
