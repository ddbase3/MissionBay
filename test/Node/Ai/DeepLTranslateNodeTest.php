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

namespace Base3\MissionBay\Test\Node\Ai;

use Base3\MissionBay\Context\AgentContext;
use Base3\MissionBay\Node\Ai\DeepLTranslateNode;
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
