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

namespace Base3\MissionBay\Test\ImageModel;

use Base3\AssistantFoundation\Api\IAiProvider;
use Base3\AssistantFoundation\Event\AiProviderRequestCompletedEvent;
use Base3\CoreFoundation\Api\IClassMap;
use Base3\Core\Event\EventManager;
use Base3\MissionBay\Ai\AiProviderRequestEventDispatcher;
use Base3\MissionBay\ImageModel\MistralImageModel;
use Base3\MissionBay\ImageModel\OpenAiImageModel;
use PHPUnit\Framework\TestCase;

final class ImageGenerationUsageTest extends TestCase {

	public function testOpenAiImageUsageIsNormalizedAndDispatched(): void {
		$provider = new RecordingImageProvider([
			'id' => 'image-request-1',
			'model' => 'gpt-image-2',
			'data' => [
				[
					'b64_json' => 'encoded-image'
				]
			],
			'usage' => [
				'input_tokens' => 18,
				'output_tokens' => 120,
				'total_tokens' => 138,
				'input_tokens_details' => [
					'text_tokens' => 18,
					'image_tokens' => 0
				],
				'output_tokens_details' => [
					'image_tokens' => 120
				]
			]
		]);
		$events = [];
		$model = new OpenAiImageModel(
			$this->createClassMap($provider),
			$this->createDispatcher($events)
		);
		$model->setOptions([
			'apikey' => 'test-key'
		]);

		$result = $model->generateResult('A lighthouse at night.');
		$usage = $result->getMetadata()->getUsage();

		$this->assertSame('/v1/images/generations', $provider->path);
		$this->assertSame('gpt-image-2', $provider->payload['model']);
		$this->assertSame(18, $usage->getInputTokens());
		$this->assertSame(120, $usage->getOutputTokens());
		$this->assertSame(138, $usage->getTotalTokens());
		$this->assertSame(18, $usage->getMetrics()['input_tokens_details.text_tokens']);
		$this->assertSame(120, $usage->getMetrics()['output_tokens_details.image_tokens']);
		$this->assertSame(1, $usage->getMetrics()['input_prompts']);
		$this->assertSame(1, $usage->getMetrics()['output_images']);
		$this->assertCount(1, $events);
		$this->assertSame('image', $events[0]->getMetadata()->getOperation());
		$this->assertSame('openaitransport', $events[0]->getMetadata()->getProvider());
	}

	public function testMistralImageRequestAndUsageUseTheSharedMetadataPath(): void {
		$provider = new RecordingImageProvider([
			'id' => 'mistral-image-request-1',
			'model' => 'mistral-small-latest',
			'choices' => [
				[
					'messages' => [
						[
							'role' => 'assistant',
							'content' => [
								[
									'type' => 'image_url',
									'image_url' => 'https://files.mistral.ai/generated/test-image.png'
								]
							]
						]
					]
				]
			],
			'usage' => [
				'prompt_tokens' => 21,
				'completion_tokens' => 9,
				'total_tokens' => 30
			]
		]);
		$events = [];
		$model = new MistralImageModel(
			$this->createClassMap($provider),
			$this->createDispatcher($events)
		);
		$model->setOptions([
			'apikey' => 'test-key',
			'tool_choice' => 'required'
		]);

		$result = $model->generateResult('A professional caregiver in a bright care facility.');
		$usage = $result->getMetadata()->getUsage();

		$this->assertSame('/v1/chat/completions', $provider->path);
		$this->assertSame('mistral-small-latest', $provider->payload['model']);
		$this->assertArrayNotHasKey('tool_choice', $provider->payload);
		$this->assertSame([['type' => 'image_generation']], $provider->payload['tools']);
		$this->assertSame('https://files.mistral.ai/generated/test-image.png', $result->getImages()[0]['url']);
		$this->assertSame(21, $usage->getInputTokens());
		$this->assertSame(9, $usage->getOutputTokens());
		$this->assertSame(30, $usage->getTotalTokens());
		$this->assertSame(1, $usage->getMetrics()['input_prompts']);
		$this->assertSame(1, $usage->getMetrics()['output_images']);
		$this->assertCount(1, $events);
		$this->assertSame('image', $events[0]->getMetadata()->getOperation());
		$this->assertSame('mistraltransport', $events[0]->getMetadata()->getProvider());
	}

	private function createClassMap(IAiProvider $provider): IClassMap {
		$classMap = $this->createStub(IClassMap::class);
		$classMap->method('getInstanceByInterfaceName')->willReturn($provider);
		return $classMap;
	}

	/**
	 * @param array<int,AiProviderRequestCompletedEvent> $events
	 */
	private function createDispatcher(array &$events): AiProviderRequestEventDispatcher {
		$eventManager = new EventManager();
		$eventManager->on(
			AiProviderRequestCompletedEvent::class,
			static function(AiProviderRequestCompletedEvent $event) use (&$events): void {
				$events[] = $event;
			}
		);

		return new AiProviderRequestEventDispatcher($eventManager);
	}
}

require_once __DIR__ . '/RecordingImageProvider.php';
