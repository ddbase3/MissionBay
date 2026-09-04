<?php declare(strict_types=1);

namespace MissionBay\Test\Service\Assistant;

use MissionBay\Orchestrator\AgentToolOrchestratorResult;
use MissionBay\Service\Assistant\AgentAssistantFallbackBuilder;
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
