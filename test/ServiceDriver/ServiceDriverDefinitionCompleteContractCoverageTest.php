<?php declare(strict_types=1);

namespace MissionBay\Test\ServiceDriver;

use AssistantFoundation\Api\IAiChatModel;
use AssistantFoundation\Api\IAiEmbeddingModel;
use AssistantFoundation\Api\IImageGenerationModel;
use AssistantFoundation\Api\IServiceDriverDefinition;
use MissionBay\Api\IParserService;
use MissionBay\Api\ISearchService;
use MissionBay\Api\ISpeechToTextDriver;
use MissionBay\Api\ITextToSpeechDriver;
use MissionBay\Api\IVectorStoreService;
use MissionBay\ServiceDriver\DoclingParserServiceDriverDefinition;
use MissionBay\ServiceDriver\MistralChatServiceDriverDefinition;
use MissionBay\ServiceDriver\MistralImageServiceDriverDefinition;
use MissionBay\ServiceDriver\MistralSpeechToTextDriverDefinition;
use MissionBay\ServiceDriver\MistralTextToSpeechDriverDefinition;
use MissionBay\ServiceDriver\MistralWebSearchServiceDriverDefinition;
use MissionBay\ServiceDriver\OpenAiChatServiceDriverDefinition;
use MissionBay\ServiceDriver\OpenAiCompatibleChatServiceDriverDefinition;
use MissionBay\ServiceDriver\OpenAiCompatibleEmbeddingServiceDriverDefinition;
use MissionBay\ServiceDriver\OpenAiCompatibleImageServiceDriverDefinition;
use MissionBay\ServiceDriver\OpenAiEmbeddingServiceDriverDefinition;
use MissionBay\ServiceDriver\OpenAiImageServiceDriverDefinition;
use MissionBay\ServiceDriver\OpenAiResponsesChatServiceDriverDefinition;
use MissionBay\ServiceDriver\OpenAiSpeechToTextDriverDefinition;
use MissionBay\ServiceDriver\OpenAiTextToSpeechDriverDefinition;
use MissionBay\ServiceDriver\OpenAiWebSearchServiceDriverDefinition;
use MissionBay\ServiceDriver\QdrantVectorStoreServiceDriverDefinition;
use MissionBay\ServiceDriver\UnstructuredParserServiceDriverDefinition;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class ServiceDriverDefinitionCompleteContractCoverageTest extends TestCase {

	/**
	 * @return array<string,array{0:class-string<IServiceDriverDefinition>,1:string,2:string,3:string,4:class-string}>
	 */
	public static function definitions(): array {
		return [
			'docling parser' => [DoclingParserServiceDriverDefinition::class, 'qualitus-docling-parser', 'parser', 'Qualitus Docling Parser', IParserService::class],
			'mistral chat' => [MistralChatServiceDriverDefinition::class, 'mistral-chat', 'llm', 'Mistral Chat', IAiChatModel::class],
			'mistral image' => [MistralImageServiceDriverDefinition::class, 'mistral-image', 'image', 'Mistral Image', IImageGenerationModel::class],
			'mistral speech to text' => [MistralSpeechToTextDriverDefinition::class, 'mistral-stt', 'stt', 'Mistral Dual-Stream Realtime Speech-to-Text', ISpeechToTextDriver::class],
			'mistral text to speech' => [MistralTextToSpeechDriverDefinition::class, 'mistral-tts', 'tts', 'Mistral Text-to-Speech', ITextToSpeechDriver::class],
			'mistral web search' => [MistralWebSearchServiceDriverDefinition::class, 'mistral-websearch', 'search', 'Mistral Web Search', ISearchService::class],
			'openai chat' => [OpenAiChatServiceDriverDefinition::class, 'openai-chat', 'llm', 'OpenAI Chat', IAiChatModel::class],
			'openai compatible chat' => [OpenAiCompatibleChatServiceDriverDefinition::class, 'openai-compatible-chat', 'llm', 'OpenAI-Compatible Chat', IAiChatModel::class],
			'openai compatible embedding' => [OpenAiCompatibleEmbeddingServiceDriverDefinition::class, 'openai-compatible-embedding', 'embedding', 'OpenAI-Compatible Embedding', IAiEmbeddingModel::class],
			'openai compatible image' => [OpenAiCompatibleImageServiceDriverDefinition::class, 'openai-compatible-image', 'image', 'OpenAI-Compatible Image', IImageGenerationModel::class],
			'openai embedding' => [OpenAiEmbeddingServiceDriverDefinition::class, 'openai-embedding', 'embedding', 'OpenAI Embedding', IAiEmbeddingModel::class],
			'openai image' => [OpenAiImageServiceDriverDefinition::class, 'openai-image', 'image', 'OpenAI Image', IImageGenerationModel::class],
			'openai responses' => [OpenAiResponsesChatServiceDriverDefinition::class, 'openai-responses', 'llm', 'OpenAI Responses', IAiChatModel::class],
			'openai speech to text' => [OpenAiSpeechToTextDriverDefinition::class, 'openai-stt', 'stt', 'OpenAI Realtime Speech-to-Text', ISpeechToTextDriver::class],
			'openai text to speech' => [OpenAiTextToSpeechDriverDefinition::class, 'openai-tts', 'tts', 'OpenAI Text-to-Speech', ITextToSpeechDriver::class],
			'openai web search' => [OpenAiWebSearchServiceDriverDefinition::class, 'openai-websearch', 'search', 'OpenAI Web Search', ISearchService::class],
			'qdrant vector store' => [QdrantVectorStoreServiceDriverDefinition::class, 'qdrant-vectorstore', 'vectorstore', 'Qdrant Vector Store', IVectorStoreService::class],
			'unstructured parser' => [UnstructuredParserServiceDriverDefinition::class, 'unstructured-parser', 'parser', 'Unstructured Parser', IParserService::class]
		];
	}

	#[DataProvider('definitions')]
	public function testClassmapIdentityAndLabelAreStable(
		string $definitionClass,
		string $driverId,
		string $serviceType,
		string $label,
		string $implementationInterface
	): void {
		$definition = new $definitionClass();
		$shortName = (new ReflectionClass($definitionClass))->getShortName();

		$this->assertSame(strtolower($shortName), $definitionClass::getName());
		$this->assertSame($label, $definition->getLabel());
		$this->assertSame($driverId, $definition->getDriver());
		$this->assertSame($serviceType, $definition->getServiceType());
		$this->assertSame($implementationInterface, $definition->getImplementationInterface());
	}

	#[DataProvider('definitions')]
	public function testEveryContractMethodReturnsConsistentRuntimeMetadata(
		string $definitionClass,
		string $driverId,
		string $serviceType,
		string $label,
		string $implementationInterface
	): void {
		$definition = new $definitionClass();
		$schema = $definition->getConfigSchema();
		$defaults = $definition->getDefaultConfig();

		$this->assertInstanceOf(IServiceDriverDefinition::class, $definition);
		$this->assertSame('object', $schema['type']);
		$this->assertIsArray($schema['properties']);
		$this->assertTrue($definition->requiresConnection());
		$this->assertSame(['http'], $definition->getSupportedConnectionTypes());
		$this->assertSame($implementationInterface, $definition->getImplementationInterface());
		$this->assertNotSame('', trim($definition->getImplementationName()));
		$this->assertSame($driverId, $defaults['driver']);
		$this->assertSame($serviceType, $defaults['serviceType']);
		$this->assertTrue($defaults['enabled']);
		$this->assertIsArray($defaults['options']);
	}

	public function testDriverIdsAndClassmapNamesAreUnique(): void {
		$drivers = [];
		$classmapNames = [];

		foreach (self::definitions() as [$definitionClass]) {
			$definition = new $definitionClass();
			$drivers[] = $definition->getDriver();
			$classmapNames[] = $definitionClass::getName();
		}

		$this->assertCount(18, array_unique($drivers));
		$this->assertCount(18, array_unique($classmapNames));
	}
}
