<?php declare(strict_types=1);

namespace MissionBay\Test\Service\Assistant;

use AssistantFoundation\Api\IAgentContextContributor;
use AssistantFoundation\Dto\AgentInstructionBlock;
use MissionBay\Api\IAgentMemoryRoleResolver;
use MissionBay\Context\AgentContext;
use MissionBay\Service\Assistant\AgentAssistantContextContributionService;
use PHPUnit\Framework\TestCase;

final class AgentAssistantContextContributionServiceTest extends TestCase {

	public function testMessagesAreOrderedByContributorAndBlockPriority(): void {
		$later = $this->contributor(10, [new AgentInstructionBlock('b', 'later', 0)]);
		$earlier = $this->contributor(-1, [new AgentInstructionBlock('x', 'second', 20), new AgentInstructionBlock('y', 'first', 0)]);
		$context = new AgentContext();
		$messages = $this->service()->buildMessages([$later, $earlier], $context);
		$this->assertSame([
			['role' => 'system', 'content' => 'first'],
			['role' => 'system', 'content' => 'second'],
			['role' => 'system', 'content' => 'later']
		], $messages);
		$this->assertCount(3, $context->getVar('agent_context_contributions'));
	}

	public function testEqualPrioritiesAreSortedByBlockId(): void {
		$resource = $this->contributor(0, [new AgentInstructionBlock('z', 'last'), new AgentInstructionBlock('a', 'first')]);
		$this->assertSame([
			['role' => 'system', 'content' => 'first'],
			['role' => 'system', 'content' => 'last']
		], $this->service()->buildMessages([$resource], new AgentContext()));
	}

	public function testSameContributorInstanceIsExecutedOnlyOnce(): void {
		$context = new AgentContext();
		$resource = $this->createMock(IAgentContextContributor::class);
		$resource->expects($this->once())->method('contribute')->with($context)->willReturn([new AgentInstructionBlock('unique', 'once')]);
		$messages = $this->service()->buildMessages([$resource, $resource], $context);
		$this->assertSame([['role' => 'system', 'content' => 'once']], $messages);
	}

	public function testInvalidBlocksAreIgnoredAndNotStoredAsDiagnostics(): void {
		$resource = $this->contributor(0, [new \stdClass(), new AgentInstructionBlock('valid', 'public')]);
		$context = new AgentContext();
		$messages = $this->service()->buildMessages([$resource], $context);
		$this->assertSame([['role' => 'system', 'content' => 'public']], $messages);
		$this->assertSame('valid', $context->getVar('agent_context_contributions')[0]['id']);
		$this->assertCount(1, $context->getVar('agent_context_contributions'));
	}

	public function testFailedContributorDoesNotAbortLaterContributions(): void {
		$bad = $this->createStub(IAgentContextContributor::class);
		$bad->method('contribute')->willThrowException(new \RuntimeException('failed'));
		$good = $this->contributor(0, [new AgentInstructionBlock('good', 'still available')]);
		$messages = $this->service()->buildMessages([$bad, $good], new AgentContext());
		$this->assertSame([['role' => 'system', 'content' => 'still available']], $messages);
	}

	public function testPlaintextIsNotStoredInDiagnosticMetadata(): void {
		$context = new AgentContext();
		$resource = $this->contributor(0, [new AgentInstructionBlock('private', 'secret content', 0, 'context')]);
		$this->service()->buildMessages([$resource], $context);
		$diagnostics = $context->getVar('agent_context_contributions');
		$this->assertSame(strlen('secret content'), $diagnostics[0]['content_length']);
		$this->assertArrayNotHasKey('content', $diagnostics[0]);
		$this->assertStringNotContainsString('secret content', json_encode($diagnostics));
	}

	private function service(): AgentAssistantContextContributionService {
		return new AgentAssistantContextContributionService($this->createStub(IAgentMemoryRoleResolver::class));
	}

	private function contributor(int $priority, array $blocks): IAgentContextContributor {
		$contributor = $this->createStub(IAgentContextContributor::class);
		$contributor->method('getPriority')->willReturn($priority);
		$contributor->method('contribute')->willReturn($blocks);
		return $contributor;
	}
}
