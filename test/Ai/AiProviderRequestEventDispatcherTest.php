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

namespace Base3\MissionBay\Test\Ai;

use Base3\AssistantFoundation\Dto\AiResultMetadata;
use Base3\AssistantFoundation\Event\AiProviderRequestCompletedEvent;
use Base3\Core\Event\EventManager;
use InvalidArgumentException;
use Base3\MissionBay\Ai\AiProviderRequestEventDispatcher;
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
