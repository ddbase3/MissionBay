<?php declare(strict_types=1);

namespace MissionBay\Test\Diagnostics;

use Base3\Database\Api\IDatabase;
use MissionBay\Diagnostics\ConversationDiagnosticInventory;
use PHPUnit\Framework\TestCase;

final class ConversationDiagnosticInventoryTest extends TestCase {

	public function testAvailableRequiresBothOwnedTables(): void {
		$db = new ConversationDiagnosticsDatabaseStub();
		$this->assertTrue((new ConversationDiagnosticInventory($db))->available());
		$db->installed = false;
		$this->assertFalse((new ConversationDiagnosticInventory($db))->available());
	}

	public function testUnavailableDatabaseIsAnExplicitError(): void {
		$db = $this->createStub(IDatabase::class);
		$db->method('connected')->willReturn(false);
		$this->expectException(\RuntimeException::class);
		(new ConversationDiagnosticInventory($db))->available();
	}

	public function testNonnumericTableIntrospectionIsRejected(): void {
		$db = $this->createStub(IDatabase::class);
		$db->method('connected')->willReturn(true);
		$db->method('scalarQuery')->willReturn('unknown');
		$this->expectException(\RuntimeException::class);
		(new ConversationDiagnosticInventory($db))->available();
	}

	public function testCountsAndReadOnlyAggregationsExposeOnlyRequestedGroups(): void {
		$db = new ConversationDiagnosticsDatabaseStub();
		$db->legacy = 4;
		$db->owners = [['owner_id' => 'native', 'conversations' => 2, 'messages' => 10]];
		$db->chatObjects = [['chatbot_config_group' => 'chat', 'chatbot_config_name' => 'default', 'conversations' => 1, 'messages' => 4]];
		$inventory = new ConversationDiagnosticInventory($db);
		$this->assertSame(4, $inventory->unresolvedOwners());
		$this->assertSame($db->owners, $inventory->owners());
		$this->assertSame($db->chatObjects, $inventory->chatbotObjects());
	}

	public function testInvalidUnresolvedOwnerCountsAreRejected(): void {
		foreach ([-1, 'NaN', '999999999999999999999'] as $count) {
			$db = $this->createStub(IDatabase::class);
			$db->method('scalarQuery')->willReturn($count);
			try {
				(new ConversationDiagnosticInventory($db))->unresolvedOwners();
				$this->fail('Invalid database count accepted.');
			} catch (\RuntimeException $exception) {
				$this->assertNotEmpty($exception->getMessage());
			}
		}
	}
}

require_once __DIR__ . '/ConversationDiagnosticsDatabaseStub.php';
