<?php declare(strict_types=1);

namespace MissionBay\Test\Audit;

use AssistantFoundation\Api\IAgentContext;
use MissionBay\Audit\AgentToolAuditContext;
use PHPUnit\Framework\TestCase;

final class AgentToolAuditContextBehaviorTest extends TestCase {

	public function testReadReturnsExistingMetadata(): void {
		$context = $this->createMock(IAgentContext::class);
		$context->expects($this->once())->method('getVar')->with(AgentToolAuditContext::CONTEXT_KEY)
			->willReturn(['source' => AgentToolAuditContext::SOURCE_MCP, 'call_id' => 'call-1']);
		$this->assertSame(['source' => 'mcp', 'call_id' => 'call-1'], AgentToolAuditContext::read($context));
	}

	public function testReadDoesNotExposeUnexpectedScalarMetadata(): void {
		$context = $this->createStub(IAgentContext::class);
		$context->method('getVar')->willReturn('private value');
		$this->assertSame([], AgentToolAuditContext::read($context));
	}

	public function testPushReplacesMetadataAndReturnsPreviousValue(): void {
		$context = $this->createMock(IAgentContext::class);
		$metadata = ['source' => AgentToolAuditContext::SOURCE_AGENT, 'call_id' => 'next'];
		$context->expects($this->once())->method('getVar')->with(AgentToolAuditContext::CONTEXT_KEY)
			->willReturn(['call_id' => 'before']);
		$context->expects($this->once())->method('setVar')->with(AgentToolAuditContext::CONTEXT_KEY, $metadata);
		$this->assertSame(['call_id' => 'before'], AgentToolAuditContext::push($context, $metadata));
	}

	public function testRestoreDeletesMissingPreviousValue(): void {
		$context = $this->createMock(IAgentContext::class);
		$context->expects($this->once())->method('forgetVar')->with(AgentToolAuditContext::CONTEXT_KEY);
		$context->expects($this->never())->method('setVar');
		AgentToolAuditContext::restore($context, null);
	}

	public function testRestorePreservesNonNullPreviousMetadata(): void {
		$context = $this->createMock(IAgentContext::class);
		$context->expects($this->once())->method('setVar')->with(AgentToolAuditContext::CONTEXT_KEY, ['legacy' => true]);
		$context->expects($this->never())->method('forgetVar');
		AgentToolAuditContext::restore($context, ['legacy' => true]);
	}

	public function testCallIdsHaveTrimmedPrefixAndIndependentRandomSuffix(): void {
		$one = AgentToolAuditContext::generateCallId('  mcp ');
		$two = AgentToolAuditContext::generateCallId('  mcp ');
		$this->assertMatchesRegularExpression('/^mcp-[a-f0-9]{32}$/', $one);
		$this->assertNotSame($one, $two);
	}

	public function testBlankCallIdPrefixUsesExistingDefault(): void {
		$this->assertMatchesRegularExpression('/^toolcall-[a-f0-9]{32}$/', AgentToolAuditContext::generateCallId('  '));
	}
}
