<?php declare(strict_types=1);

namespace MissionBay\Test\Node\Core;

use Base3\Configuration\Api\IConfiguration;
use MissionBay\Context\AgentContext;
use MissionBay\Node\Core\GetConfigurationNode;
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
