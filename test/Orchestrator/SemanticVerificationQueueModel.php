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
use Base3\AssistantFoundation\Dto\AgentCapability;
use Base3\AssistantFoundation\Dto\AgentCapabilityCatalog;
use Base3\AssistantFoundation\Dto\AgentCapabilitySelectionConfig;
use Base3\AssistantFoundation\Dto\AgentResultVerification;
use Base3\AssistantFoundation\Dto\AgentToolResult;
use Base3\MissionBay\ChatModel\NormalizedChatModelTrait;
use Base3\MissionBay\Context\AgentContext;
use Base3\MissionBay\Orchestrator\Stage\AgentSemanticVerificationStage;
use Base3\MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys;
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
