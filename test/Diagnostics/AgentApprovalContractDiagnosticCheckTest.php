<?php declare(strict_types=1);

namespace Base3\MissionBay\Test\Diagnostics;

use Base3\CoreFoundation\Diagnostics\Dto\DiagnosticCheckResult;
use Base3\MissionBay\Diagnostics\AgentApprovalContractDiagnosticCheck;
use Base3\MissionBay\Diagnostics\Scope\AgentExecutionDiagnosticScope;
use PHPUnit\Framework\TestCase;

final class AgentApprovalContractDiagnosticCheckTest extends TestCase {

	public function testNewCheckUsesExistingAgentExecutionScopeWithoutAnyDependencies(): void {
		$check = new AgentApprovalContractDiagnosticCheck();
		$this->assertSame('agentapprovalcontractdiagnosticcheck', $check::getName());
		$this->assertSame(AgentExecutionDiagnosticScope::getName(), $check->getScope());
		foreach (range(1, 3) as $attempt) {
			$result = $check->run();
			$this->assertSame(DiagnosticCheckResult::PASSED, $result->status);
			$this->assertSame(['checked' => 8], $result->details);
			$this->assertSame('', $result->remediationKey);
		}
	}
}
