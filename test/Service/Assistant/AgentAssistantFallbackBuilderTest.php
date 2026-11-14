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

namespace Base3\MissionBay\Test\Service\Assistant;

use Base3\MissionBay\Orchestrator\AgentToolOrchestratorResult;
use Base3\MissionBay\Service\Assistant\AgentAssistantFallbackBuilder;
use PHPUnit\Framework\TestCase;

final class AgentAssistantFallbackBuilderTest extends TestCase {

	public function testModelFailureShowsConcreteSanitizedCause(): void {
		$result = new AgentToolOrchestratorResult(
			messages: [['role' => 'user', 'content' => 'Run the request.']],
			finalAssistantMessage: null,
			completed: false,
			iterations: 1,
			failureCode: 'model_raw_error',
			failureMessage: 'Model call failed: HTTP 429',
			failureDetail: [
				'type' => \RuntimeException::class,
				'message' => 'HTTP 429 Authorization: Bearer secret-token',
				'code' => 429
			]
		);

		$message = (new AgentAssistantFallbackBuilder())->build($result);

		$this->assertStringContainsString('Modellfehler: RuntimeException: HTTP 429', $message);
		$this->assertStringContainsString('[REDACTED]', $message);
		$this->assertStringNotContainsString('secret-token', $message);
	}
}
