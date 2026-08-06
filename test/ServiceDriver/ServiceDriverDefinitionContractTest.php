<?php declare(strict_types=1);

namespace MissionBay\Test\ServiceDriver;

use AssistantFoundation\Api\IServiceDriverDefinition;
use MissionBay\ServiceDriver\DoclingParserServiceDriverDefinition;
use MissionBay\ServiceDriver\MistralChatServiceDriverDefinition;
use MissionBay\ServiceDriver\MistralImageServiceDriverDefinition;
use MissionBay\ServiceDriver\MistralRealtimeSpeechToTextDriverDefinition;
use MissionBay\ServiceDriver\MistralTextToSpeechDriverDefinition;
use MissionBay\ServiceDriver\MistralWebSearchServiceDriverDefinition;
use MissionBay\ServiceDriver\OpenAiChatServiceDriverDefinition;
use MissionBay\ServiceDriver\OpenAiCompatibleChatServiceDriverDefinition;
use MissionBay\ServiceDriver\OpenAiCompatibleEmbeddingServiceDriverDefinition;
use MissionBay\ServiceDriver\OpenAiCompatibleImageServiceDriverDefinition;
use MissionBay\ServiceDriver\OpenAiEmbeddingServiceDriverDefinition;
use MissionBay\ServiceDriver\OpenAiImageServiceDriverDefinition;
use MissionBay\ServiceDriver\OpenAiRealtimeSpeechToTextDriverDefinition;
use MissionBay\ServiceDriver\OpenAiTextToSpeechDriverDefinition;
use MissionBay\ServiceDriver\OpenAiWebSearchServiceDriverDefinition;
use MissionBay\ServiceDriver\QdrantVectorStoreServiceDriverDefinition;
use MissionBay\ServiceDriver\UnstructuredParserServiceDriverDefinition;
use PHPUnit\Framework\TestCase;

final class ServiceDriverDefinitionContractTest extends TestCase {

	/**
	 * @return array<string,array{0:class-string<IServiceDriverDefinition>}>
	 */
	public static function definitionClasses(): array {
		return [
			'docling parser' => [DoclingParserServiceDriverDefinition::class],
			'mistral chat' => [MistralChatServiceDriverDefinition::class],
			'mistral image' => [MistralImageServiceDriverDefinition::class],
			'mistral realtime speech-to-text' => [MistralRealtimeSpeechToTextDriverDefinition::class],
			'mistral text-to-speech' => [MistralTextToSpeechDriverDefinition::class],
			'mistral web search' => [MistralWebSearchServiceDriverDefinition::class],
			'openai chat' => [OpenAiChatServiceDriverDefinition::class],
			'openai compatible chat' => [OpenAiCompatibleChatServiceDriverDefinition::class],
			'openai compatible embedding' => [OpenAiCompatibleEmbeddingServiceDriverDefinition::class],
			'openai compatible image' => [OpenAiCompatibleImageServiceDriverDefinition::class],
			'openai embedding' => [OpenAiEmbeddingServiceDriverDefinition::class],
			'openai image' => [OpenAiImageServiceDriverDefinition::class],
			'openai realtime speech-to-text' => [OpenAiRealtimeSpeechToTextDriverDefinition::class],
			'openai text-to-speech' => [OpenAiTextToSpeechDriverDefinition::class],
			'openai web search' => [OpenAiWebSearchServiceDriverDefinition::class],
			'qdrant vector store' => [QdrantVectorStoreServiceDriverDefinition::class],
			'unstructured parser' => [UnstructuredParserServiceDriverDefinition::class]
		];
	}

	/** @dataProvider definitionClasses */
	public function testEveryBuiltInDefinitionUsesTheFoundationContract(string $definitionClass): void {
		$definition = new $definitionClass();

		$this->assertInstanceOf(IServiceDriverDefinition::class, $definition);
		$this->assertNotSame('', trim($definition->getDriver()));
		$this->assertNotSame('', trim($definition->getServiceType()));
		$this->assertNotSame('', trim($definition->getImplementationInterface()));
		$this->assertNotSame('', trim($definition->getImplementationName()));
		$this->assertTrue(interface_exists($definition->getImplementationInterface()));
	}

	/** @dataProvider definitionClasses */
	public function testServiceSchemasDoNotOwnConnectionCredentials(string $definitionClass): void {
		$definition = new $definitionClass();
		$schema = $definition->getConfigSchema();
		$properties = is_array($schema['properties'] ?? null) ? $schema['properties'] : [];

		foreach([
			'baseUrl',
			'base_url',
			'endpoint',
			'apiKey',
			'apikey',
			'api_key',
			'auth',
			'authType',
			'auth_type',
			'authHeaderName',
			'auth_header_name',
			'authSecret',
			'auth_secret',
			'secret',
			'secretMode',
			'secretValue'
		] as $connectionKey) {
			$this->assertArrayNotHasKey($connectionKey, $properties);
		}
	}
}
