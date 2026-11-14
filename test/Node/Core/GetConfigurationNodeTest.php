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

namespace Base3\MissionBay\Test\Node\Core;

use Base3\CoreFoundation\Configuration\Api\IConfiguration;
use Base3\MissionBay\Context\AgentContext;
use Base3\MissionBay\Node\Core\GetConfigurationNode;
use PHPUnit\Framework\TestCase;

final class GetConfigurationNodeTest extends TestCase {

	public function testExistingSectionKeyCanReturnNullAndFalse(): void {
		$config = $this->createStub(IConfiguration::class);
		$config->method('get')->willReturn(['nullable' => null, 'disabled' => false]);
		$node = new GetConfigurationNode($config);
		$context = new AgentContext();
		$this->assertSame(['value' => null], $node->execute(['section' => 'provider', 'key' => 'nullable'], [], $context));
		$this->assertSame(['value' => false], $node->execute(['section' => 'provider', 'key' => 'disabled'], [], $context));
	}

	public function testInvalidSectionAndAbsentKeyReturnErrors(): void {
		$config = $this->createStub(IConfiguration::class);
		$config->method('get')->willReturn(['name' => 'present']);
		$node = new GetConfigurationNode($config, 'config-node');
		$this->assertStringContainsString('not found', $node->execute(['section' => 's', 'key' => 'missing'], [], new AgentContext())['error']);
		$this->assertStringContainsString('config-node', $node->execute(['section' => 's', 'key' => 'missing'], [], new AgentContext())['error']);
	}

	public function testNonArrayConfigSectionIsRejected(): void {
		$config = $this->createStub(IConfiguration::class);
		$config->method('get')->willReturn('invalid');
		$this->assertStringContainsString('not found or invalid', (new GetConfigurationNode($config))->execute(['section' => 's', 'key' => 'key'], [], new AgentContext())['error']);
	}

	public function testMissingKeyOrSectionNeverCallsConfigBackend(): void {
		$config = $this->createMock(IConfiguration::class);
		$config->expects($this->never())->method('get');
		$node = new GetConfigurationNode($config);
		$this->assertStringContainsString('Missing section or key', $node->execute(['section' => '', 'key' => 'k'], [], new AgentContext())['error']);
		$this->assertStringContainsString('Missing section or key', $node->execute(['section' => 's'], [], new AgentContext())['error']);
	}
}
