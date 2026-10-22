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

namespace MissionBay\Resource;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentConversationMemory;
use AssistantFoundation\Dto\AgentConversation;
use AssistantFoundation\Dto\AgentConversationScope;
use Base3\Usermanager\Api\IUsermanager;
use Base3\Translation\Api\ITranslation;
use MissionBay\Agent\AgentNodeDock;

/**
 * One conversation-memory component exposing two docked storage backends.
 * The host-selected identity determines which child can become active.
 * Neither child accesses its storage during eager preset materialization.
 */
final class RoutedConversationMemoryAgentResource extends AbstractAgentResource implements IAgentConversationMemory {

	private ?IAgentConversationMemory $authenticated = null;
	private ?IAgentConversationMemory $anonymous = null;

	public function __construct(
		private readonly IUsermanager $usermanager,
		private readonly ITranslation $translation,
		?string $id = null
	) {
		parent::__construct($id);
	}

	public static function getName(): string {
		return 'routedconversationmemoryagentresource';
	}

	public function getDescription(): string {
		return $this->translation->translate('ConversationMemory', 'router', 'description');
	}

	/** @return AgentNodeDock[] */
	public function getDockDefinitions(): array {
		return [
			new AgentNodeDock(
				name: 'authenticated',
				description: $this->translation->translate('ConversationMemory', 'router', 'authenticated'),
				interface: IAgentConversationMemory::class,
				maxConnections: 1,
				required: true
			),
			new AgentNodeDock(
				name: 'anonymous',
				description: $this->translation->translate('ConversationMemory', 'router', 'anonymous'),
				interface: IAgentConversationMemory::class,
				maxConnections: 1,
				required: true
			)
		];
	}

	public function init(array $resources, IAgentContext $context): void {
		$authenticated = $resources['authenticated'][0] ?? null;
		$anonymous = $resources['anonymous'][0] ?? null;
		if (!$authenticated instanceof IAgentConversationMemory || !$anonymous instanceof IAgentConversationMemory) {
			throw new \RuntimeException('Conversation router requires authenticated and anonymous memory resources.');
		}
		if ($authenticated === $anonymous) {
			throw new \RuntimeException('Conversation router requires two distinct memory resources.');
		}
		$this->authenticated = $authenticated;
		$this->anonymous = $anonymous;
	}

	public function bindConversationScope(AgentConversationScope $scope): void {
		$memory = $this->memory();
		$userId = $this->currentUserId();
		$expected = $userId !== null
			? AgentConversationScope::OWNER_USER : AgentConversationScope::OWNER_SESSION;
		if ($scope->getOwnerType() !== $expected
			|| ($expected === AgentConversationScope::OWNER_USER && $scope->getOwnerId() !== $userId)) {
			throw new \RuntimeException('Conversation scope does not match the current authenticated identity.');
		}
		$memory->bindConversationScope($scope);
	}

	public function listConversations(): array {
		return $this->memory()->listConversations();
	}

	public function getConversation(string $conversationId): ?AgentConversation {
		return $this->memory()->getConversation($conversationId);
	}

	public function getActiveConversation(): ?AgentConversation {
		return $this->memory()->getActiveConversation();
	}

	public function createConversation(
		?string $conversationId = null,
		string $title = '',
		string $titleSource = AgentConversation::TITLE_SOURCE_TEMPORARY,
		string $openingMessage = ''
	): AgentConversation {
		return $this->memory()->createConversation($conversationId, $title, $titleSource, $openingMessage);
	}

	public function activateConversation(string $conversationId): AgentConversation {
		return $this->memory()->activateConversation($conversationId);
	}

	public function renameConversation(
		string $conversationId,
		string $title,
		string $titleSource = AgentConversation::TITLE_SOURCE_MANUAL
	): AgentConversation {
		return $this->memory()->renameConversation($conversationId, $title, $titleSource);
	}

	public function deleteConversation(string $conversationId): void {
		$this->memory()->deleteConversation($conversationId);
	}

	public function deleteConversationsByOwner(string $ownerType, string $ownerId): void {
		// Administrative cleanup must reach persistent data even if no user is signed in.
		if ($ownerType === AgentConversationScope::OWNER_USER) {
			$this->requireAuthenticated()->deleteConversationsByOwner($ownerType, $ownerId);
			return;
		}
		if ($ownerType === AgentConversationScope::OWNER_SESSION) {
			// Old database rows must be deleted even if the session backend cannot
			// access the requested (possibly expired) session.
			$this->requireAuthenticated()->deleteConversationsByOwner($ownerType, $ownerId);
			$this->requireAnonymous()->deleteConversationsByOwner($ownerType, $ownerId);
			return;
		}
		throw new \InvalidArgumentException('Unknown conversation owner type.');
	}

	public function deleteConversationsByChannel(string $channelId): void {
		$this->requireAuthenticated()->deleteConversationsByChannel($channelId);
		$this->requireAnonymous()->deleteConversationsByChannel($channelId);
	}

	public function touchConversation(string $conversationId): AgentConversation {
		return $this->memory()->touchConversation($conversationId);
	}

	public function loadNodeHistory(string $nodeId): array {
		return $this->memory()->loadNodeHistory($nodeId);
	}

	public function appendNodeHistory(string $nodeId, array $message): void {
		$this->memory()->appendNodeHistory($nodeId, $message);
	}

	public function setFeedback(string $nodeId, string $messageId, ?string $feedback): bool {
		return $this->memory()->setFeedback($nodeId, $messageId, $feedback);
	}

	public function updateNodeHistoryMessageMetadata(string $nodeId, string $messageId, array $metadata): bool {
		return $this->memory()->updateNodeHistoryMessageMetadata($nodeId, $messageId, $metadata);
	}

	public function resetNodeHistory(string $nodeId): void {
		$this->memory()->resetNodeHistory($nodeId);
	}

	public function getPriority(): int {
		return $this->memory()->getPriority();
	}

	private function memory(): IAgentConversationMemory {
		// Never cache a selection across an authentication-state transition.
		return $this->currentUserId() !== null
			? $this->requireAuthenticated() : $this->requireAnonymous();
	}

	/** The active IUsermanager adapter defines authentication, not an ILIAS ID convention. */
	private function currentUserId(): ?string {
		$user = $this->usermanager->getUser();
		if ($user === null) {
			return null;
		}
		if (!is_string($user->id) && !is_int($user->id)) {
			throw new \RuntimeException('Authenticated conversation owner has no native user ID.');
		}
		$id = (string)$user->id;
		if (trim($id) === '' || strlen($id) > 191) {
			throw new \RuntimeException('Authenticated conversation owner has an invalid native user ID.');
		}
		return $id;
	}

	private function requireAuthenticated(): IAgentConversationMemory {
		if ($this->authenticated === null) {
			throw new \RuntimeException('Authenticated conversation memory is not connected.');
		}
		return $this->authenticated;
	}

	private function requireAnonymous(): IAgentConversationMemory {
		if ($this->anonymous === null) {
			throw new \RuntimeException('Anonymous conversation memory is not connected.');
		}
		return $this->anonymous;
	}
}
