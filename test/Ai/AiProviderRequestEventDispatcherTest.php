<?php declare(strict_types=1);

namespace MissionBay\Test\Ai;

use AssistantFoundation\Dto\AiResultMetadata;
use AssistantFoundation\Event\AiProviderRequestCompletedEvent;
use Base3\Event\EventManager;
use InvalidArgumentException;
use MissionBay\Ai\AiProviderRequestEventDispatcher;
use PHPUnit\Framework\TestCase;

final class AiProviderRequestEventDispatcherTest extends TestCase {

	public function testDispatchesTypedCompletedEvent(): void {
		$eventManager = new EventManager();
		$events = [];
		$eventManager->on(
			AiProviderRequestCompletedEvent::class,
			static function(AiProviderRequestCompletedEvent $event) use (&$events): void {
				$events[] = $event;
			}
		);
		$dispatcher = new AiProviderRequestEventDispatcher($eventManager);
		$metadata = new AiResultMetadata('embedding', 'openai', 'text-embedding-test');

		$dispatcher->dispatch($metadata, 'openaiembeddingmodel');

		$this->assertCount(1, $events);
		$this->assertSame($metadata, $events[0]->getMetadata());
		$this->assertSame('openaiembeddingmodel', $events[0]->getSourceName());
		$this->assertGreaterThan(0, $events[0]->getOccurredAt());
	}

	public function testRejectsEmptySourceName(): void {
		$dispatcher = new AiProviderRequestEventDispatcher(new EventManager());

		$this->expectException(InvalidArgumentException::class);
		$dispatcher->dispatch(new AiResultMetadata('chat'), ' ');
	}
}
