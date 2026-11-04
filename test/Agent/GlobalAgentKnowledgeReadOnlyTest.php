<?php declare(strict_types=1);

namespace MissionBay\Test\Agent;

use Base3\Accesscontrol\Api\IAccesscontrol;
use Base3\Database\Api\IDatabase;
use Base3\Session\Api\ISession;
use MissionBay\Agent\GlobalAgentKnowledgeService;
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
