<?php declare(strict_types=1);

namespace MissionBay\Test\Orchestrator;

use Base3\Event\EventManager;
use MissionBay\Ai\AiProviderRequestEventDispatcher;

use AssistantFoundation\Api\IAiChatModel;
use AssistantFoundation\Dto\AgentCapability;
use AssistantFoundation\Dto\AgentCapabilityCatalog;
use AssistantFoundation\Dto\AgentCapabilitySelectionConfig;
use AssistantFoundation\Dto\AgentResultVerification;
use AssistantFoundation\Dto\AgentToolResult;
use MissionBay\ChatModel\NormalizedChatModelTrait;
use MissionBay\Context\AgentContext;
use MissionBay\Orchestrator\Stage\AgentSemanticVerificationStage;
use MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys;
use PHPUnit\Framework\TestCase;

final class SemanticVerificationQueueModel implements IAiChatModel {

	use NormalizedChatModelTrait;

	public static function getName(): string {
		return 'semanticverificationqueuemodel';
	}

	/** @var array<int,mixed> */
	private array $responses;

	/** @var array<int,array{0:array<int,array<string,mixed>>,1:array<int,array<string,mixed>>}> */
	private array $calls = [];

	/**
	 * @param array<int,mixed> $responses
	 */
	public function __construct(array $responses) {
		$this->responses = $responses;
		$this->initializeProviderRequestEvents(new AiProviderRequestEventDispatcher(new EventManager()));
	}

	public function chat(array $messages): string {
		return '';
	}

	public function raw(array $messages, array $tools = []): mixed {
		$this->calls[] = [$messages, $tools];
		$response = array_shift($this->responses);

		if ($response instanceof \Throwable) {
			throw $response;
		}

		return $response;
	}

	/**
	 * @return array<int,array{0:array<int,array<string,mixed>>,1:array<int,array<string,mixed>>}>
	 */
	public function getCalls(): array {
		return $this->calls;
	}

	public function stream(array $messages, array $tools, callable $onData, ?callable $onMeta = null): void {
	}

	public function setOptions(array $options): void {
	}

	public function getOptions(): array {
		return [];
	}
}
