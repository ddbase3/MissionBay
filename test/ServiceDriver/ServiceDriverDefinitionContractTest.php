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

namespace Base3\MissionBay\Test\ServiceDriver;

use Base3\AssistantFoundation\Api\IServiceDriverDefinition;
use Base3\MissionBay\ServiceDriver\DoclingParserServiceDriverDefinition;
use Base3\MissionBay\ServiceDriver\MistralChatServiceDriverDefinition;
use Base3\MissionBay\ServiceDriver\MistralImageServiceDriverDefinition;
use Base3\MissionBay\ServiceDriver\MistralSpeechToTextDriverDefinition;
use Base3\MissionBay\ServiceDriver\MistralTextToSpeechDriverDefinition;
use Base3\MissionBay\ServiceDriver\MistralWebSearchServiceDriverDefinition;
use Base3\MissionBay\ServiceDriver\OpenAiChatServiceDriverDefinition;
use Base3\MissionBay\ServiceDriver\OpenAiResponsesChatServiceDriverDefinition;
use Base3\MissionBay\ServiceDriver\OpenAiCompatibleChatServiceDriverDefinition;
use Base3\MissionBay\ServiceDriver\OpenAiCompatibleEmbeddingServiceDriverDefinition;
use Base3\MissionBay\ServiceDriver\OpenAiCompatibleImageServiceDriverDefinition;
use Base3\MissionBay\ServiceDriver\OpenAiEmbeddingServiceDriverDefinition;
use Base3\MissionBay\ServiceDriver\OpenAiImageServiceDriverDefinition;
use Base3\MissionBay\ServiceDriver\OpenAiSpeechToTextDriverDefinition;
use Base3\MissionBay\ServiceDriver\OpenAiTextToSpeechDriverDefinition;
use Base3\MissionBay\ServiceDriver\OpenAiWebSearchServiceDriverDefinition;
use Base3\MissionBay\ServiceDriver\QdrantVectorStoreServiceDriverDefinition;
use Base3\MissionBay\ServiceDriver\UnstructuredParserServiceDriverDefinition;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class ServiceDriverDefinitionContractTest extends TestCase {

	/**
	 * @return array<string,array{0:class-string<IServiceDriverDefinition>}>
	 */
	public static function definitionClasses(): array {
		return [
			'docling parser' => [DoclingParserServiceDriverDefinition::class],
			'mistral chat' => [MistralChatServiceDriverDefinition::class],
			'mistral image' => [MistralImageServiceDriverDefinition::class],
			'mistral speech-to-text' => [MistralSpeechToTextDriverDefinition::class],
			'mistral text-to-speech' => [MistralTextToSpeechDriverDefinition::class],
			'mistral web search' => [MistralWebSearchServiceDriverDefinition::class],
			'openai chat' => [OpenAiChatServiceDriverDefinition::class],
			'openai responses' => [OpenAiResponsesChatServiceDriverDefinition::class],
			'openai compatible chat' => [OpenAiCompatibleChatServiceDriverDefinition::class],
			'openai compatible embedding' => [OpenAiCompatibleEmbeddingServiceDriverDefinition::class],
			'openai compatible image' => [OpenAiCompatibleImageServiceDriverDefinition::class],
			'openai embedding' => [OpenAiEmbeddingServiceDriverDefinition::class],
			'openai image' => [OpenAiImageServiceDriverDefinition::class],
			'openai speech-to-text' => [OpenAiSpeechToTextDriverDefinition::class],
			'openai text-to-speech' => [OpenAiTextToSpeechDriverDefinition::class],
			'openai web search' => [OpenAiWebSearchServiceDriverDefinition::class],
			'qdrant vector store' => [QdrantVectorStoreServiceDriverDefinition::class],
			'unstructured parser' => [UnstructuredParserServiceDriverDefinition::class]
		];
	}

	#[DataProvider('definitionClasses')]
	public function testEveryBuiltInDefinitionUsesTheFoundationContract(string $definitionClass): void {
		$definition = new $definitionClass();

		$this->assertInstanceOf(IServiceDriverDefinition::class, $definition);
		$this->assertNotSame('', trim($definition->getDriver()));
		$this->assertNotSame('', trim($definition->getServiceType()));
		$this->assertNotSame('', trim($definition->getImplementationInterface()));
		$this->assertNotSame('', trim($definition->getImplementationName()));
		$this->assertTrue(interface_exists($definition->getImplementationInterface()));
	}

	#[DataProvider('definitionClasses')]
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
