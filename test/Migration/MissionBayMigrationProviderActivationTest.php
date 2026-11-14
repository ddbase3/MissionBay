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

namespace Base3\MissionBay\Test\Migration;

use Base3\CoreFoundation\Api\IContainer;
use Base3\CoreFoundation\Database\Api\IDatabase;
use Base3\CoreFoundation\Migration\Api\IDatabaseMigrationProvider;
use Base3\MissionBay\Migration\Migration001AnonymizeAiUsage;
use Base3\MissionBay\Migration\Migration001ConversationIdentityAndEncryption;
use Base3\MissionBay\Migration\MissionBayAiUsageMigrationProvider;
use Base3\MissionBay\Migration\MissionBayConversationMigrationProvider;
use PHPUnit\Framework\TestCase;

final class MissionBayMigrationProviderActivationTest extends TestCase {

	public function testBothProvidersAreDisabledWithoutDatabaseBinding(): void {
		$container = $this->createMock(IContainer::class);
		$container->expects($this->exactly(2))->method('has')->with(IDatabase::class)->willReturn(false);
		$this->assertFalse((new MissionBayAiUsageMigrationProvider($container))->isActive());
		$this->assertFalse((new MissionBayConversationMigrationProvider($container))->isActive());
	}

	public function testBothProvidersActivateWithDatabaseBinding(): void {
		$container = $this->createMock(IContainer::class);
		$container->expects($this->exactly(2))->method('has')->with(IDatabase::class)->willReturn(true);
		$this->assertTrue((new MissionBayAiUsageMigrationProvider($container))->isActive());
		$this->assertTrue((new MissionBayConversationMigrationProvider($container))->isActive());
	}

	public function testAiUsageProviderOwnsOnlyTheAiUsageMigration(): void {
		$provider = new MissionBayAiUsageMigrationProvider($this->createStub(IContainer::class));
		$this->assertInstanceOf(IDatabaseMigrationProvider::class, $provider);
		$this->assertSame('missionbayaiusagemigrationprovider', MissionBayAiUsageMigrationProvider::getName());
		$this->assertSame([Migration001AnonymizeAiUsage::class], $provider->getMigrations());
	}

	public function testConversationProviderOwnsOnlyConversationMigration(): void {
		$provider = new MissionBayConversationMigrationProvider($this->createStub(IContainer::class));
		$this->assertInstanceOf(IDatabaseMigrationProvider::class, $provider);
		$this->assertSame('missionbayconversationmigrationprovider', MissionBayConversationMigrationProvider::getName());
		$this->assertSame([Migration001ConversationIdentityAndEncryption::class], $provider->getMigrations());
	}
}
