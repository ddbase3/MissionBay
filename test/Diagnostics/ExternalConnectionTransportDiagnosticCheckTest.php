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

namespace Base3\MissionBay\Test\Diagnostics;

use Base3\CoreFoundation\Diagnostics\Dto\DiagnosticCheckResult;
use Base3\CoreFoundation\Settings\Api\ISettingsStore;
use Base3\MissionBay\Diagnostics\ExternalConnectionTransportDiagnosticCheck;
use PHPUnit\Framework\TestCase;

final class ExternalConnectionTransportDiagnosticCheckTest extends TestCase {

	public function testRemoteHttpConnectionFailsWithoutExposingEndpoint(): void {
		$result = $this->check([
			'unsafe' => ['id' => 'unsafe', 'type' => 'http', 'baseUrl' => 'http://service.example/secret', 'enabled' => true],
			'safe' => ['id' => 'safe', 'type' => 'http', 'baseUrl' => 'https://service.example', 'enabled' => true],
			'localhost' => ['id' => 'localhost', 'type' => 'http', 'baseUrl' => 'http://127.0.0.1:8000', 'enabled' => true],
			'off' => ['id' => 'off', 'type' => 'http', 'baseUrl' => 'http://service.example', 'enabled' => false]
		]);
		$this->assertSame(DiagnosticCheckResult::FAILED, $result->status);
		$this->assertSame(2, $result->details['remoteConnections']);
		$this->assertSame(1, $result->details['insecureConnections']);
		$this->assertStringNotContainsString('service.example', json_encode($result));
	}

	public function testMissingRemoteUrlIsWarning(): void {
		$result = $this->check(['missing' => ['id' => 'missing', 'type' => 'http', 'baseUrl' => '', 'enabled' => true]]);
		$this->assertSame(DiagnosticCheckResult::WARNING, $result->status);
		$this->assertSame(1, $result->details['unverifiableConnections']);
	}

	public function testNoRelevantConnectionsIsNotApplicable(): void {
		$this->assertSame(DiagnosticCheckResult::NOT_APPLICABLE, $this->check([])->status);
	}

	private function check(array $records): DiagnosticCheckResult {
		$settings = $this->createMock(ISettingsStore::class);
		$settings->expects($this->once())->method('getGroup')->with('connection')->willReturn($records);
		return (new ExternalConnectionTransportDiagnosticCheck($settings))->run();
	}

}
