<?php declare(strict_types=1);

namespace MissionBay\Test\Migration;

use Base3\Api\IContainer;
use Base3\Database\Api\IDatabase;
use Base3\Migration\Api\IDatabaseMigrationProvider;
use MissionBay\Migration\Migration001AnonymizeAiUsage;
use MissionBay\Migration\Migration001ConversationIdentityAndEncryption;
use MissionBay\Migration\MissionBayAiUsageMigrationProvider;
use MissionBay\Migration\MissionBayConversationMigrationProvider;
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
