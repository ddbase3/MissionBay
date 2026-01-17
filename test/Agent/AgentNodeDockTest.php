<?php declare(strict_types=1);

namespace MissionBay\Test\Agent;

use PHPUnit\Framework\TestCase;
use MissionBay\Agent\AgentNodeDock;

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
