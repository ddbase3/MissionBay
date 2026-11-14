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

use Base3\CoreFoundation\Accesscontrol\Api\IAccesscontrol;
use Base3\CoreFoundation\Database\Api\IDatabase;
use Base3\CoreFoundation\Session\Api\ISession;
use Base3\MissionBay\Agent\GlobalAgentKnowledgeService;
use PHPUnit\Framework\TestCase;

final class GlobalAgentKnowledgeReadOnlyTest extends TestCase {

	public function testGlobalWritesAreRejectedBeforeDatabaseAccess(): void {
		$db = $this->createMock(IDatabase::class);
		$db->expects($this->never())->method('connect');
		$db->expects($this->never())->method('nonQuery');
		$service = new GlobalAgentKnowledgeService($db, $this->createStub(IAccesscontrol::class), $this->createStub(ISession::class));
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Global knowledge entries must be seeded');
		$service->createEntry(['scope' => 'global']);
	}

	public function testGlobalScopeIsCaseAndWhitespaceInsensitive(): void {
		$db = $this->createMock(IDatabase::class);
		$db->expects($this->never())->method('connect');
		$service = new GlobalAgentKnowledgeService($db, $this->createStub(IAccesscontrol::class), $this->createStub(ISession::class));
		$this->expectException(\InvalidArgumentException::class);
		$service->createEntry(['scope' => '  GLOBAL  ']);
	}

	public function testInheritedSupportedMemoryTypesRemainAvailable(): void {
		$service = new GlobalAgentKnowledgeService($this->createStub(IDatabase::class), $this->createStub(IAccesscontrol::class), $this->createStub(ISession::class));
		$this->assertSame(['task', 'episodic', 'semantic', 'procedural'], $service->getMemoryTypes());
		$this->assertContains('valid', $service->getAllowedStatuses('semantic'));
		$this->assertContains('closed', $service->getAllowedStatuses('task'));
		$this->assertSame([], $service->getAllowedStatuses('unknown'));
	}
}
