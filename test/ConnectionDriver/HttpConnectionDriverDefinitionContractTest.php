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

namespace Base3\MissionBay\Test\ConnectionDriver;

use Base3\AssistantFoundation\Api\IConnectionDriverDefinition;
use Base3\MissionBay\ConnectionDriver\HttpConnectionDriverDefinition;
use PHPUnit\Framework\TestCase;

final class HttpConnectionDriverDefinitionContractTest extends TestCase {

	public function testIdentityIsStableForClassMapAndTransportType(): void {
		$definition = new HttpConnectionDriverDefinition();
		$this->assertInstanceOf(IConnectionDriverDefinition::class, $definition);
		$this->assertSame('httpconnectiondriverdefinition', HttpConnectionDriverDefinition::getName());
		$this->assertSame('http', $definition->getDriver());
		$this->assertSame('http', $definition->getConnectionType());
		$this->assertSame('HTTP', $definition->getLabel());
	}

	public function testSchemaKeepsAuthenticationAndTimeoutInConnectionOwner(): void {
		$properties = (new HttpConnectionDriverDefinition())->getConfigSchema()['properties'];
		$this->assertTrue($properties['baseUrl']['required']);
		$this->assertSame(['none', 'bearer', 'api-key', 'basic'], $properties['auth.type']['enum']);
		$this->assertSame('object', $properties['auth.secret']['type']);
		$this->assertSame(60, $properties['timeoutSeconds']['default']);
	}

	public function testDefaultSecretHasNoActualCredential(): void {
		$defaults = (new HttpConnectionDriverDefinition())->getDefaultConfig();
		$this->assertSame('http', $defaults['driver']);
		$this->assertSame('', $defaults['baseUrl']);
		$this->assertSame('Authorization', $defaults['auth']['headerName']);
		$this->assertSame(['mode' => 'fixed', 'value' => ''], $defaults['auth']['secret']);
		$this->assertSame([], $defaults['options']);
	}

	public function testHealthCheckUsesSafeReadOnlyGet(): void {
		$this->assertSame(['type' => 'http', 'method' => 'GET', 'path' => '', 'expectStatus' => [200]],
			(new HttpConnectionDriverDefinition())->getHealthCheckSchema());
	}
}
