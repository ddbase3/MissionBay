<?php declare(strict_types=1);

namespace MissionBay\Test\Node\Ai;

use MissionBay\Context\AgentContext;
use MissionBay\Node\Ai\DeepLTranslateNode;
use PHPUnit\Framework\TestCase;

final class DeepLTranslateNodeTest extends TestCase {

	public function testMissingRequiredInputsAreRejectedWithoutNetwork(): void {
		$node = new DeepLTranslateNode();
		$context = new AgentContext();
		foreach ([
			[],
			['apikey' => '', 'text' => 'hello', 'target' => 'de'],
			['apikey' => 'key', 'text' => '  ', 'target' => 'de'],
			['apikey' => 'key', 'text' => 'hello', 'target' => '  ']
		] as $inputs) {
			$this->assertSame(['error' => 'Missing required input: apikey, text or target.'], $node->execute($inputs, [], $context));
		}
	}

	public function testDefinitionsExposeRequiredFieldsWithoutLeakingSecrets(): void {
		$node = new DeepLTranslateNode();
		$this->assertSame('deepltranslatenode', $node::getName());
		$ports = $node->getInputDefinitions();
		$this->assertSame(['apikey', 'text', 'source', 'target'], array_map(static fn($port) => $port->name, $ports));
		$this->assertFalse($ports[2]->required);
		$this->assertSame(['translated', 'error'], array_map(static fn($port) => $port->name, $node->getOutputDefinitions()));
	}
}
