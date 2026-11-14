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
use Base3\MissionBay\Agent\AgentNodeDock;

final class AgentNodeDockTest extends TestCase {

    public function testAgentNodeDockConstructorAndToArray(): void {
        $dock = new AgentNodeDock(
            'logger',
            'Logs events from various sources',
            'LoggerInterface',
            5,
            true
        );

        // Ensure the object is created with the expected values
        $this->assertSame('logger', $dock->name);
        $this->assertSame('Logs events from various sources', $dock->description);
        $this->assertSame('LoggerInterface', $dock->interface);
        $this->assertSame(5, $dock->maxConnections);
        $this->assertTrue($dock->required);

        // Ensure toArray() returns the expected associative array
        $expected = [
            'name' => 'logger',
            'description' => 'Logs events from various sources',
            'interface' => 'LoggerInterface',
            'maxConnections' => 5,
            'required' => true
        ];

        $this->assertSame($expected, $dock->toArray());
    }

    public function testAgentNodeDockWithDefaultValues(): void {
        $dock = new AgentNodeDock('storage');

        // Ensure default values are applied where not specified
        $this->assertSame('storage', $dock->name);
        $this->assertSame('', $dock->description);
        $this->assertSame('', $dock->interface);
        $this->assertNull($dock->maxConnections);
        $this->assertFalse($dock->required);

        // Ensure toArray() reflects the default values
        $expected = [
            'name' => 'storage',
            'description' => '',
            'interface' => '',
            'maxConnections' => null,
            'required' => false
        ];

        $this->assertSame($expected, $dock->toArray());
    }
}
