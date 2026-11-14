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

namespace Base3\MissionBay\Test\Agent;

use PHPUnit\Framework\TestCase;
use Base3\MissionBay\Agent\AgentNodePort;

final class AgentNodePortTest extends TestCase {

	public function testConstructorSetsAllPropertiesExplicitly(): void {
		$port = new AgentNodePort(
			name: 'text',
			description: 'Input text',
			type: 'string',
			default: 'hello',
			required: true
		);

		$this->assertSame('text', $port->name);
		$this->assertSame('Input text', $port->description);
		$this->assertSame('string', $port->type);
		$this->assertSame('hello', $port->default);
		$this->assertTrue($port->required);
	}

	public function testConstructorAppliesDefaultValues(): void {
		$port = new AgentNodePort('result');

		$this->assertSame('result', $port->name);
		$this->assertSame('', $port->description);
		$this->assertSame('string', $port->type);
		$this->assertNull($port->default);
		$this->assertTrue($port->required);
	}

	public function testOptionalPortWithDefaultValue(): void {
		$port = new AgentNodePort(
			name: 'limit',
			description: 'Max results',
			type: 'int',
			default: 10,
			required: false
		);

		$this->assertFalse($port->required);
		$this->assertSame(10, $port->default);
	}

	public function testToArrayReturnsExpectedStructure(): void {
		$port = new AgentNodePort(
			name: 'items',
			description: 'List of items',
			type: 'array<string>',
			default: [],
			required: false
		);

		$this->assertSame(
			[
				'name'        => 'items',
				'description' => 'List of items',
				'type'        => 'array<string>',
				'default'     => [],
				'required'    => false,
			],
			$port->toArray()
		);
	}
}
