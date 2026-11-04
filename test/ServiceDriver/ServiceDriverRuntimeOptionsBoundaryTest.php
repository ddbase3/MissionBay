<?php declare(strict_types=1);

namespace MissionBay\Test\ServiceDriver;

use AssistantFoundation\Api\IServiceDriverDefinition;
use MissionBay\ServiceDriver\DoclingParserServiceDriverDefinition;
use MissionBay\ServiceDriver\MistralWebSearchServiceDriverDefinition;
use MissionBay\ServiceDriver\OpenAiChatServiceDriverDefinition;
use MissionBay\ServiceDriver\OpenAiCompatibleChatServiceDriverDefinition;
use MissionBay\ServiceDriver\OpenAiCompatibleEmbeddingServiceDriverDefinition;
use MissionBay\ServiceDriver\OpenAiCompatibleImageServiceDriverDefinition;
use MissionBay\ServiceDriver\OpenAiEmbeddingServiceDriverDefinition;
use MissionBay\ServiceDriver\OpenAiImageServiceDriverDefinition;
use MissionBay\ServiceDriver\OpenAiResponsesChatServiceDriverDefinition;
use MissionBay\ServiceDriver\OpenAiWebSearchServiceDriverDefinition;
use MissionBay\ServiceDriver\QdrantVectorStoreServiceDriverDefinition;
use MissionBay\ServiceDriver\UnstructuredParserServiceDriverDefinition;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ServiceDriverRuntimeOptionsBoundaryTest extends TestCase {

	/** @return array<string,array{0:class-string<IServiceDriverDefinition>}> */
	public static function untestedDefinitions(): array {
		return [
			'docling' => [DoclingParserServiceDriverDefinition::class],
			'mistral web search' => [MistralWebSearchServiceDriverDefinition::class],
			'openai chat' => [OpenAiChatServiceDriverDefinition::class],
			'openai compatible chat' => [OpenAiCompatibleChatServiceDriverDefinition::class],
			'openai compatible embedding' => [OpenAiCompatibleEmbeddingServiceDriverDefinition::class],
			'openai compatible image' => [OpenAiCompatibleImageServiceDriverDefinition::class],
			'openai embedding' => [OpenAiEmbeddingServiceDriverDefinition::class],
			'openai image' => [OpenAiImageServiceDriverDefinition::class],
			'openai responses' => [OpenAiResponsesChatServiceDriverDefinition::class],
			'openai web search' => [OpenAiWebSearchServiceDriverDefinition::class],
			'qdrant vectorstore' => [QdrantVectorStoreServiceDriverDefinition::class],
			'unstructured' => [UnstructuredParserServiceDriverDefinition::class]
		];
	}

	#[DataProvider('untestedDefinitions')]
	public function testAllDefinitionsRequireExistingHttpConnection(string $class): void {
		$driver = new $class();
		$this->assertTrue($driver->requiresConnection());
		$this->assertSame(['http'], $driver->getSupportedConnectionTypes());
	}

	#[DataProvider('untestedDefinitions')]
	public function testDefaultConfigurationCannotRedefineConnectionCredentials(string $class): void {
		$driver = new $class();
		$defaults = $driver->getDefaultConfig();
		$this->assertSame($driver->getDriver(), $defaults['driver']);
		$this->assertSame($driver->getServiceType(), $defaults['serviceType']);
		$this->assertTrue($defaults['enabled']);
		$this->assertIsArray($defaults['options']);
		foreach (['apiKey', 'baseUrl', 'secret', 'auth', 'token', 'password'] as $connectionKey) {
			$this->assertArrayNotHasKey($connectionKey, $defaults['options']);
		}
	}

	public function testOpenAiImageSchemaMapsPublicNamesToSupportedRuntimeKeys(): void {
		$properties = (new OpenAiImageServiceDriverDefinition())->getConfigSchema()['properties'];
		$this->assertSame('output_format', $properties['outputFormat']['runtimeKey']);
		$this->assertSame('n', $properties['numberOfImages']['runtimeKey']);
		$this->assertSame(['png', 'jpeg', 'webp'], $properties['outputFormat']['enum']);
		$this->assertSame(0, $properties['outputCompression']['minimum']);
		$this->assertSame(100, $properties['outputCompression']['maximum']);
	}

	public function testParserDefinitionsUseDistinctMultipartFieldsAndPriorities(): void {
		$docling = new DoclingParserServiceDriverDefinition();
		$unstructured = new UnstructuredParserServiceDriverDefinition();
		$this->assertSame('file', $docling->getDefaultConfig()['options']['fileField']);
		$this->assertSame('files', $unstructured->getDefaultConfig()['options']['fileField']);
		$this->assertSame(45, $docling->getConfigSchema()['properties']['priority']['default']);
		$this->assertSame(35, $unstructured->getConfigSchema()['properties']['priority']['default']);
		$this->assertContains('pdf', $docling->getDefaultConfig()['options']['supportedExtensions']);
		$this->assertContains('epub', $unstructured->getDefaultConfig()['options']['supportedExtensions']);
	}

	public function testCompatibleChatAndEmbeddingRequireExplicitModelSelection(): void {
		foreach ([new OpenAiCompatibleChatServiceDriverDefinition(), new OpenAiCompatibleEmbeddingServiceDriverDefinition()] as $definition) {
			$this->assertSame('', $definition->getDefaultConfig()['model']);
			$this->assertTrue($definition->getConfigSchema()['properties']['model']['required']);
		}
		$this->assertSame(false, (new OpenAiCompatibleEmbeddingServiceDriverDefinition())->getDefaultConfig()['options']['normalizeVectors']);
	}

	public function testMistralSearchModelIsExplicitlyRequired(): void {
		$driver = new MistralWebSearchServiceDriverDefinition();
		$this->assertTrue($driver->getConfigSchema()['modelRequired']);
		$this->assertTrue($driver->getDefaultConfig()['requiresModel']);
		$this->assertSame(10, $driver->getDefaultConfig()['options']['maxResults']);
		$this->assertSame([], $driver->getDefaultConfig()['options']['blockedDomains']);
	}

	public function testQdrantPayloadIndexConfigurationIsExposed(): void {
		$driver = new QdrantVectorStoreServiceDriverDefinition();
		$this->assertSame('boolean', $driver->getConfigSchema()['properties']['createPayloadIndexes']['type']);
		$this->assertTrue($driver->getDefaultConfig()['options']['createPayloadIndexes']);
		$this->assertSame(90, $driver->getDefaultConfig()['options']['timeoutSeconds']);
	}
}
