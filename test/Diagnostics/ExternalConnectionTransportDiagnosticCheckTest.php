<?php declare(strict_types=1);

namespace MissionBay\Test\Diagnostics;

use Base3\Diagnostics\Dto\DiagnosticCheckResult;
use Base3\Settings\Api\ISettingsStore;
use MissionBay\Diagnostics\ExternalConnectionTransportDiagnosticCheck;
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
