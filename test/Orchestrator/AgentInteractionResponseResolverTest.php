<?php declare(strict_types=1);

namespace MissionBay\Test\Orchestrator;

use AssistantFoundation\Api\IAiChatModel;
use AssistantFoundation\Dto\AgentAction;
use AssistantFoundation\Dto\AgentInteractionRequest;
use AssistantFoundation\Dto\AgentInteractionResponse;
use MissionBay\ChatModel\NormalizedChatModelTrait;
use MissionBay\Dto\Assistant\AgentInteractionResolution;
use MissionBay\Orchestrator\Service\AgentInteractionResponseResolver;
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

final class InteractionResolverQueueChatModel implements IAiChatModel {
	use NormalizedChatModelTrait;

	/** @var array<int,mixed> */
	private array $responses;

	/** @param array<int,mixed> $responses */
	public function __construct(array $responses) {
		$this->responses = $responses;
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
