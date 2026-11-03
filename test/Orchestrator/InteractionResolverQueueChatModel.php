<?php declare(strict_types=1);

namespace MissionBay\Test\Orchestrator;

use Base3\Event\EventManager;
use MissionBay\Ai\AiProviderRequestEventDispatcher;

use AssistantFoundation\Api\IAiChatModel;
use AssistantFoundation\Dto\AgentAction;
use AssistantFoundation\Dto\AgentInteractionRequest;
use AssistantFoundation\Dto\AgentInteractionResponse;
use MissionBay\ChatModel\NormalizedChatModelTrait;
use MissionBay\Dto\Assistant\AgentInteractionResolution;
use MissionBay\Orchestrator\Service\AgentInteractionResponseResolver;
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
	public function stream(array $messages, array $tools, callable $onData, callable $onMeta = null): void {}
	public function setOptions(array $options): void {}
	public function getOptions(): array { return []; }
}
