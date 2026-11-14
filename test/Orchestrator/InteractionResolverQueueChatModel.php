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

namespace Base3\MissionBay\Test\Orchestrator;

use Base3\Core\Event\EventManager;
use Base3\MissionBay\Ai\AiProviderRequestEventDispatcher;

use Base3\AssistantFoundation\Api\IAiChatModel;
use Base3\AssistantFoundation\Dto\AgentAction;
use Base3\AssistantFoundation\Dto\AgentInteractionRequest;
use Base3\AssistantFoundation\Dto\AgentInteractionResponse;
use Base3\MissionBay\ChatModel\NormalizedChatModelTrait;
use Base3\MissionBay\Dto\Assistant\AgentInteractionResolution;
use Base3\MissionBay\Orchestrator\Service\AgentInteractionResponseResolver;
use PHPUnit\Framework\TestCase;

final class InteractionResolverQueueChatModel implements IAiChatModel {
	use NormalizedChatModelTrait;

	public static function getName(): string {
		return 'interactionresolverqueuechatmodel';
	}

	/** @var array<int,mixed> */
	private array $responses;

	/** @param array<int,mixed> $responses */
	public function __construct(array $responses) {
		$this->responses = $responses;
		$this->initializeProviderRequestEvents(new AiProviderRequestEventDispatcher(new EventManager()));
	}

	public function chat(array $messages): string { return ''; }
	public function raw(array $messages, array $tools = []): mixed {
		if ($this->responses === []) {
			throw new \RuntimeException('No queued model response available.');
		}
		return array_shift($this->responses);
	}
	public function stream(array $messages, array $tools, callable $onData, ?callable $onMeta = null): void {}
	public function setOptions(array $options): void {}
	public function getOptions(): array { return []; }
}
