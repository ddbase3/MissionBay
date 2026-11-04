<?php declare(strict_types=1);

namespace MissionBay\Test\Dto\Assistant;

use AssistantFoundation\Dto\AgentInteractionResponse;
use MissionBay\Dto\Assistant\AgentInteractionResolution;
use PHPUnit\Framework\TestCase;

final class AgentInteractionResolutionTest extends TestCase {

	public function testResolvedRetainsExplicitResponseAndMetadata(): void {
		$response = new AgentInteractionResponse('request-1', AgentInteractionResponse::DECISION_DENY, [], 'not now');
		$resolution = AgentInteractionResolution::resolved([$response], 'explicit decision', ['trusted' => true]);
		$this->assertTrue($resolution->isResolved());
		$this->assertFalse($resolution->isUnclear());
		$this->assertSame([$response], $resolution->getResponses());
		$this->assertSame('explicit decision', $resolution->getReason());
		$this->assertSame(['trusted' => true], $resolution->getMetadata());
		$this->assertSame($response->toArray(), $resolution->toArray()['responses'][0]);
	}

	public function testUnclearHasNoDecisionsAndPreservesReason(): void {
		$resolution = AgentInteractionResolution::unclear('ambiguous answer', ['attempt' => 2]);
		$this->assertFalse($resolution->isResolved());
		$this->assertTrue($resolution->isUnclear());
		$this->assertSame([], $resolution->getResponses());
		$this->assertSame('unclear', $resolution->toArray()['status']);
		$this->assertSame(['attempt' => 2], $resolution->toArray()['metadata']);
	}

	public function testUnsupportedStatusIsRejected(): void {
		$this->expectException(\InvalidArgumentException::class);
		new AgentInteractionResolution('approved');
	}

	public function testResolvedStateRequiresAtLeastOneResponse(): void {
		$this->expectException(\InvalidArgumentException::class);
		AgentInteractionResolution::resolved([]);
	}

	public function testEveryResponseMustHaveTheDeclaredType(): void {
		$this->expectException(\InvalidArgumentException::class);
		AgentInteractionResolution::resolved([['request_id' => 'not-an-object']]);
	}
}
