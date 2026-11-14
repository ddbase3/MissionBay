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

namespace Base3\MissionBay\Test\ChatModel;

use Base3\AssistantFoundation\Dto\AiChatResult;
use Base3\AssistantFoundation\Event\AiProviderRequestCompletedEvent;
use Base3\Core\Event\EventManager;
use Base3\MissionBay\Ai\AiProviderRequestEventDispatcher;
use Base3\MissionBay\ChatModel\NormalizedChatModelTrait;
use PHPUnit\Framework\TestCase;

final class NormalizedChatModelTraitTest extends TestCase {

	public function testStreamResultReturnsContentMetadataAndReconstructedToolCalls(): void {
		$events = [];
		$model = new NormalizedChatModelTraitTestDouble1($this->createDispatcher($events));
		$deltas = [];
		$metadataEvents = [];

		$result = $model->streamResult(
			[['role' => 'user', 'content' => 'Look it up']],
			[['type' => 'function']],
			function(string $delta) use (&$deltas): void {
				$deltas[] = $delta;
			},
			function(array $metadata) use (&$metadataEvents): void {
				$metadataEvents[] = $metadata;
			}
		);

		$this->assertInstanceOf(AiChatResult::class, $result);
		$this->assertSame(['I will check.'], $deltas);
		$this->assertCount(3, $metadataEvents);
		$this->assertSame('I will check.', $result->getContent());
		$this->assertSame('tool_calls', $result->getMetadata()->getFinishReason());
		$this->assertCount(1, $result->getToolCalls());
		$this->assertSame('call-1', $result->getToolCalls()[0]->getId());
		$this->assertSame('lookup', $result->getToolCalls()[0]->getName());
		$this->assertSame(['query' => 'BASE3'], $result->getToolCalls()[0]->getArguments());
		$this->assertSame($metadataEvents, $result->getRaw());
		$this->assertCount(1, $events);
		$this->assertSame(11, $events[0]->getUsage()->getTotalTokens());
	}


	public function testStreamResultPreservesTextOnlyCompletion(): void {
		$events = [];
		$model = new NormalizedChatModelTraitTestDouble2($this->createDispatcher($events));
		$deltas = [];

		$result = $model->streamResult(
			[['role' => 'user', 'content' => 'Hello']],
			[],
			function(string $delta) use (&$deltas): void {
				$deltas[] = $delta;
			}
		);

		$this->assertSame(['Hello', ' world'], $deltas);
		$this->assertSame('Hello world', $result->getContent());
		$this->assertFalse($result->hasToolCalls());
		$this->assertSame('stop', $result->getMetadata()->getFinishReason());
		$this->assertCount(1, $events);
	}

	public function testStreamResultRejectsToolFinishWithoutToolMetadata(): void {
		$events = [];
		$model = new NormalizedChatModelTraitTestDouble3($this->createDispatcher($events));

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('did not provide reconstructable tool-call metadata');

		$model->streamResult(
			[['role' => 'user', 'content' => 'Look it up']],
			[['type' => 'function']],
			static function(string $delta): void {},
			static function(array $metadata): void {}
		);
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

require_once __DIR__ . '/NormalizedChatModelTraitTestDouble1.php';

require_once __DIR__ . '/NormalizedChatModelTraitTestDouble2.php';

require_once __DIR__ . '/NormalizedChatModelTraitTestDouble3.php';
