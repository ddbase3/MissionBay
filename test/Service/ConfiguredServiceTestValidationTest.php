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

namespace Base3\MissionBay\Test\Service;

use Base3\CoreFoundation\Api\IClassMap;
use Base3\CoreFoundation\Logger\Api\ILogger;
use Base3\CoreFoundation\Settings\Api\ISettingsStore;
use Base3\MissionBay\Api\IAgentConfigValueResolver;
use Base3\MissionBay\Service\ConfiguredServiceRuntimeResolver;
use Base3\MissionBay\Service\ConfiguredServiceTestService;
use PHPUnit\Framework\TestCase;

final class ConfiguredServiceTestValidationTest extends TestCase {

	public function testTesterExposesStableServiceType(): void {
		$this->assertSame('missionbay-configured-service', ConfiguredServiceTestService::getType());
	}

	public function testMissingIdIsRejectedWithoutContactingProvider(): void {
		$result = $this->tester()->test(['settings' => ['serviceType' => 'llm']]);
		$this->assertFalse($result['ok']);
		$this->assertSame('Missing configured service id.', $result['message']);
		$this->assertSame('', $result['details']['serviceId']);
		$this->assertArrayHasKey('durationMs', $result['details']);
	}

	public function testWhitespaceOnlyIdIsRejected(): void {
		$result = $this->tester()->test(["id" => " \t ", 'settings' => ['serviceType' => 'llm']]);
		$this->assertFalse($result['ok']);
		$this->assertSame('', $result['details']['serviceId']);
	}

	public function testMissingSettingsAreRejectedWithoutContactingProvider(): void {
		$result = $this->tester()->test(['id' => 'service-1']);
		$this->assertFalse($result['ok']);
		$this->assertSame('Missing configured service settings.', $result['message']);
		$this->assertSame('service-1', $result['details']['serviceId']);
	}

	public function testUnexpectedSettingsShapeIsRejected(): void {
		$result = $this->tester()->test(['id' => 'service-1', 'settings' => 'invalid']);
		$this->assertFalse($result['ok']);
		$this->assertSame('Missing configured service settings.', $result['message']);
	}

	public function testUnsupportedServiceTypeHasStructuredFailureAndDiagnosticMetadata(): void {
		$result = $this->tester()->test(['id' => '  My Service! ', 'settings' => [
			'serviceType' => 'unknown',
			'driver' => 'driver A',
			'connection' => 'connection 1',
			'model' => '  model-x  '
		]]);
		$this->assertFalse($result['ok']);
		$this->assertStringContainsString('No configured service test is available', $result['message']);
		$this->assertSame('myservice', $result['details']['serviceId']);
		$this->assertSame('unknown', $result['details']['serviceType']);
		$this->assertSame('drivera', $result['details']['driver']);
		$this->assertSame('connection1', $result['details']['connectionId']);
		$this->assertSame('model-x', $result['details']['model']);
	}

	public function testEmptyServiceTypeFailsWithoutSelectingDefaultBackend(): void {
		$result = $this->tester()->test(['id' => 'service', 'settings' => ['enabled' => true]]);
		$this->assertFalse($result['ok']);
		$this->assertSame('', $result['details']['serviceType']);
	}

	public function testUnsupportedTypeFailureIsLoggedWithFixedScope(): void {
		$logger = $this->createMock(ILogger::class);
		$logger->expects($this->once())->method('error')->with(
			'Configured service test failed.',
			$this->callback(static fn(array $data): bool =>
				($data['scope'] ?? null) === 'service-test'
				&& ($data['service_id'] ?? null) === 'service'
				&& ($data['service_type'] ?? null) === 'missing'
			)
		);
		$this->tester($logger)->test(['id' => 'service', 'settings' => ['serviceType' => 'missing']]);
	}

	private function tester(?ILogger $logger = null): ConfiguredServiceTestService {
		$settings = $this->createStub(ISettingsStore::class);
		$resolver = $this->createStub(IAgentConfigValueResolver::class);
		$runtime = new ConfiguredServiceRuntimeResolver($settings, $this->createStub(IClassMap::class), $resolver);
		return new ConfiguredServiceTestService($runtime, $logger ?? $this->createStub(ILogger::class));
	}
}
