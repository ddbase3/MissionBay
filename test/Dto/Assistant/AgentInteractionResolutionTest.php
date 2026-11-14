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

namespace Base3\MissionBay\Test\Dto\Assistant;

use Base3\AssistantFoundation\Dto\AgentInteractionResponse;
use Base3\MissionBay\Dto\Assistant\AgentInteractionResolution;
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
