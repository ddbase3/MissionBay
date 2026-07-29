<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 **********************************************************************/

namespace MissionBay\Service;

use AssistantFoundation\Api\IAgentConversationMemory;
use AssistantFoundation\Api\IAgentConversationRuntimeService;
use AssistantFoundation\Dto\AgentConversation;
use AssistantFoundation\Dto\AgentConversationRequest;
use AssistantFoundation\Dto\AgentConversationState;
use MissionBay\Api\IAgentComponentPresetMaterializer;
use MissionBay\Profile\AgentMemoryProfileResolver;

/**
 * MissionBay runtime access to the one configured conversation memory.
 */
final class AgentConversationService implements IAgentConversationRuntimeService {

	private const DEFAULT_ASSISTANT_NODE_ID = 'assistant';

	public function __construct(
		private readonly AgentMemoryProfileResolver $memoryProfileResolver,
		private readonly IAgentComponentPresetMaterializer $presetMaterializer
	) {}

	public static function getName(): string {
		return 'missionbayagentconversationservice';
	}

	public static function getRuntimeId(): string {
		return 'missionbay';
	}

	public function getState(AgentConversationRequest $request, string $conversationId = ''): AgentConversationState {
		[$memory, $nodeId, $warnings] = $this->resolveMemory($request);
		return $this->buildState($memory, $nodeId, $warnings, $conversationId);
	}

	public function createConversation(
		AgentConversationRequest $request,
		?string $conversationId = null,
		string $title = '',
		string $titleSource = AgentConversation::TITLE_SOURCE_TEMPORARY,
		string $openingMessage = ''
	): AgentConversationState {
		[$memory, $nodeId, $warnings] = $this->resolveMemory($request);
		$conversation = $memory->createConversation(
			$conversationId,
			$title,
			$titleSource,
			$openingMessage
		);

		return $this->buildState($memory, $nodeId, $warnings, $conversation->getId());
	}

	public function activateConversation(AgentConversationRequest $request, string $conversationId): AgentConversationState {
		[$memory, $nodeId, $warnings] = $this->resolveMemory($request);
		$memory->activateConversation($conversationId);

		return $this->buildState($memory, $nodeId, $warnings, $conversationId, false);
	}

	public function renameConversation(
		AgentConversationRequest $request,
		string $conversationId,
		string $title,
		string $titleSource = AgentConversation::TITLE_SOURCE_MANUAL
	): AgentConversationState {
		[$memory, $nodeId, $warnings] = $this->resolveMemory($request);
		$memory->renameConversation($conversationId, $title, $titleSource);

		return $this->buildState($memory, $nodeId, $warnings, $conversationId);
	}

	public function deleteConversation(AgentConversationRequest $request, string $conversationId): AgentConversationState {
		[$memory, $nodeId, $warnings] = $this->resolveMemory($request);
		$memory->deleteConversation($conversationId);

		return $this->buildState($memory, $nodeId, $warnings);
	}

	public function appendMessage(
		AgentConversationRequest $request,
		string $conversationId,
		array $message
	): AgentConversationState {
		[$memory, $nodeId, $warnings] = $this->resolveMemory($request);
		$conversationId = trim($conversationId);
		if ($conversationId === '') {
			throw new \InvalidArgumentException('Conversation id is required when appending a message.');
		}

		$memory->activateConversation($conversationId);
		$memory->appendNodeHistory($nodeId, $this->normalizeMessage($message));

		return $this->buildState($memory, $nodeId, $warnings, $conversationId, false);
	}

	public function touchConversation(AgentConversationRequest $request, string $conversationId): AgentConversationState {
		[$memory, $nodeId, $warnings] = $this->resolveMemory($request);
		$memory->touchConversation($conversationId);

		return $this->buildState($memory, $nodeId, $warnings, $conversationId, false);
	}

	/**
	 * @return array{0:IAgentConversationMemory,1:string,2:array<int,string>}
	 */
	private function resolveMemory(AgentConversationRequest $request): array {
		$configuration = $request->getAgentConfiguration();
		$profileId = $this->normalizeTechnicalId((string)($configuration['memory_profile'] ?? ''));
		if ($profileId === '') {
			throw new \RuntimeException('Agent conversation access requires a configured memory_profile.');
		}

		$components = $this->memoryProfileResolver->resolveComponents($profileId);
		if (count($components) !== 1) {
			throw new \RuntimeException('Agent conversation access requires exactly one conversation memory.');
		}

		$presetId = trim((string)($components[0]['preset'] ?? ''));
		if ($presetId === '') {
			throw new \RuntimeException('Memory profile contains no usable conversation-memory preset.');
		}

		$contextVars = $request->getContext();
		unset($contextVars['conversation_id']);
		$channelId = trim((string)($contextVars['conversation_channel_id'] ?? ''));
		if ($channelId === '') {
			throw new \RuntimeException('Agent conversation access requires context variable conversation_channel_id.');
		}
		$contextVars['source'] = 'agent-conversation-service';

		$context = $this->presetMaterializer->createContext($contextVars);
		$materialization = $this->presetMaterializer->materialize($presetId, $context);
		$memory = $materialization->getMemory();
		if (!$memory instanceof IAgentConversationMemory) {
			throw new \RuntimeException('Configured memory profile did not materialize an IAgentConversationMemory.');
		}

		$nodeId = $request->getNodeId();
		if ($nodeId === '') {
			$nodeId = trim((string)($configuration['agent_components_assistant_node'] ?? ''));
		}
		if ($nodeId === '') {
			$nodeId = self::DEFAULT_ASSISTANT_NODE_ID;
		}

		return [$memory, $nodeId, $materialization->getWarnings()];
	}

	/** @param array<int,string> $warnings */
	private function buildState(
		IAgentConversationMemory $memory,
		string $nodeId,
		array $warnings,
		string $conversationId = '',
		bool $activate = true
	): AgentConversationState {
		$conversationId = trim($conversationId);
		$active = null;

		if ($conversationId !== '') {
			$active = $activate
				? $memory->activateConversation($conversationId)
				: $memory->getConversation($conversationId);
			if (!$active instanceof AgentConversation) {
				throw new \RuntimeException('Conversation not found: ' . $conversationId);
			}
		}
		else {
			$active = $memory->getActiveConversation();
			if ($active instanceof AgentConversation) {
				$active = $memory->activateConversation($active->getId());
			}
		}

		$messages = $active instanceof AgentConversation
			? $memory->loadNodeHistory($nodeId)
			: [];

		return new AgentConversationState(
			$memory->listConversations(),
			$active,
			$messages,
			$nodeId,
			array_values(array_unique($warnings))
		);
	}

	/** @param array<string,mixed> $message @return array<string,mixed> */
	private function normalizeMessage(array $message): array {
		$id = trim((string)($message['id'] ?? ''));
		$role = strtolower(trim((string)($message['role'] ?? '')));
		$content = trim((string)($message['content'] ?? ''));
		if ($id === '' || preg_match('/^[A-Za-z0-9._:-]+$/', $id) !== 1) {
			throw new \InvalidArgumentException('Conversation message requires a valid id.');
		}
		if (!in_array($role, ['user', 'assistant'], true)) {
			throw new \InvalidArgumentException('Conversation message contains an invalid role.');
		}
		if ($content === '') {
			throw new \InvalidArgumentException('Conversation message content must not be empty.');
		}

		return [
			'id' => $id,
			'role' => $role,
			'content' => $content,
			'timestamp' => trim((string)($message['timestamp'] ?? '')),
			'feedback' => $message['feedback'] ?? null
		];
	}

	private function normalizeTechnicalId(string $value): string {
		$value = strtolower(trim($value));
		return preg_replace('/[^a-z0-9._-]+/', '', $value) ?? '';
	}
}
