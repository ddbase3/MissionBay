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

use Base3\AssistantFoundation\Api\IAiChatModel;
use Base3\AssistantFoundation\Dto\AgentAction;
use Base3\AssistantFoundation\Dto\AgentInteractionRequest;
use Base3\AssistantFoundation\Dto\AgentInteractionResponse;
use Base3\MissionBay\ChatModel\NormalizedChatModelTrait;
use Base3\MissionBay\Dto\Assistant\AgentInteractionResolution;
use Base3\MissionBay\Orchestrator\Service\AgentInteractionResponseResolver;
use PHPUnit\Framework\TestCase;

final class AgentInteractionResponseResolverTest extends TestCase {

	public function testNaturalLanguageApprovalIsResolvedByModelOutput(): void {
		$resolution = (new AgentInteractionResponseResolver())->resolve(
			new InteractionResolverQueueChatModel([$this->modelResponse([
				'status' => 'resolved',
				'reason' => 'The user approved the action.',
				'responses' => [[
					'request_id' => 'air-1',
					'decision' => 'approve',
					'input' => []
				]]
			])]),
			[$this->approvalRequest('air-1')],
			'jo hau rein'
		);

		$this->assertTrue($resolution->isResolved());
		$this->assertSame(AgentInteractionResponse::DECISION_APPROVE, $resolution->getResponses()[0]->getDecision());
	}


	public function testNaturalLanguageRevisionCanSubmitAChangedApprovalPayload(): void {
		$resolution = (new AgentInteractionResponseResolver())->resolve(
			new InteractionResolverQueueChatModel([$this->modelResponse([
				'status' => 'resolved',
				'reason' => 'The user requested a concrete revision.',
				'responses' => [[
					'request_id' => 'air-1',
					'decision' => 'submit',
					'input' => ['id' => 42, 'title' => 'Changed title']
				]]
			])]),
			[$this->approvalRequest('air-1')],
			'Ändere bitte nur den Titel auf Changed title.'
		);

		$this->assertTrue($resolution->isResolved());
		$this->assertSame(AgentInteractionResponse::DECISION_SUBMIT, $resolution->getResponses()[0]->getDecision());
		$this->assertSame(['id' => 42, 'title' => 'Changed title'], $resolution->getResponses()[0]->getInput());
	}

	public function testNaturalLanguageDenialIsResolvedByModelOutput(): void {
		$resolution = (new AgentInteractionResponseResolver())->resolve(
			new InteractionResolverQueueChatModel([$this->modelResponse([
				'status' => 'resolved',
				'reason' => 'The user declined.',
				'responses' => [[
					'request_id' => 'air-1',
					'decision' => 'deny',
					'input' => []
				]]
			])]),
			[$this->approvalRequest('air-1')],
			'nein, lass das'
		);

		$this->assertTrue($resolution->isResolved());
		$this->assertSame(AgentInteractionResponse::DECISION_DENY, $resolution->getResponses()[0]->getDecision());
	}

	public function testUnclearModelDecisionKeepsInteractionOpen(): void {
		$resolution = (new AgentInteractionResponseResolver())->resolve(
			new InteractionResolverQueueChatModel([$this->modelResponse([
				'status' => 'unclear',
				'reason' => 'The reply does not contain a decision.',
				'responses' => []
			])]),
			[$this->approvalRequest('air-1')],
			'was genau meinst du?'
		);

		$this->assertSame(AgentInteractionResolution::STATUS_UNCLEAR, $resolution->getStatus());
		$this->assertSame([], $resolution->getResponses());
	}

	public function testInvalidModelResponseIsUnclear(): void {
		$resolution = (new AgentInteractionResponseResolver())->resolve(
			new InteractionResolverQueueChatModel([$this->rawContent('not-json')]),
			[$this->approvalRequest('air-1')],
			'ok'
		);

		$this->assertTrue($resolution->isUnclear());
	}

	public function testModelFailureIsUnclear(): void {
		$resolution = (new AgentInteractionResponseResolver())->resolve(
			new InteractionResolverQueueChatModel([]),
			[$this->approvalRequest('air-1')],
			'ok'
		);

		$this->assertTrue($resolution->isUnclear());
		$this->assertSame('error', $resolution->getMetadata()['parse_status'] ?? null);
	}

	public function testEveryPendingRequestMustBeResolvedExactlyOnce(): void {
		$resolution = (new AgentInteractionResponseResolver())->resolve(
			new InteractionResolverQueueChatModel([$this->modelResponse([
				'status' => 'resolved',
				'reason' => 'Only one request was returned.',
				'responses' => [[
					'request_id' => 'air-1',
					'decision' => 'approve',
					'input' => []
				]]
			])]),
			[$this->approvalRequest('air-1'), $this->approvalRequest('air-2')],
			'ja zu allem'
		);

		$this->assertTrue($resolution->isUnclear());
	}

	private function approvalRequest(string $id): AgentInteractionRequest {
		$action = new AgentAction('call-' . $id, AgentAction::TYPE_TOOL_CALL, 'update_record', ['id' => 42]);

		return new AgentInteractionRequest(
			$id,
			AgentInteractionRequest::KIND_APPROVAL,
			$action,
			str_repeat('a', 64),
			'Confirm update',
			'Update record 42?',
			['tool' => 'update_record', 'input' => ['id' => 42]],
			'medium'
		);
	}

	/** @param array<string,mixed> $payload @return array<string,mixed> */
	private function modelResponse(array $payload): array {
		return $this->rawContent(json_encode($payload, JSON_THROW_ON_ERROR));
	}

	/** @return array<string,mixed> */
	private function rawContent(string $content): array {
		return ['choices' => [['message' => ['role' => 'assistant', 'content' => $content]]]];
	}
}

require_once __DIR__ . '/InteractionResolverQueueChatModel.php';
