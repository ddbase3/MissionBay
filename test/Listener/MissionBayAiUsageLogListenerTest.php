<?php declare(strict_types=1);

namespace MissionBay\Test\Listener;

use AssistantFoundation\Dto\AiResultMetadata;
use AssistantFoundation\Dto\AiUsage;
use AssistantFoundation\Event\AiProviderRequestCompletedEvent;
use Base3\Database\Api\IDatabase;
use Base3\Logger\Api\ILogger;
use MissionBay\Listener\MissionBayAiUsageLogListener;
use PHPUnit\Framework\TestCase;

final class MissionBayAiUsageLogListenerTest extends TestCase {

	public function testStoresOneAppendOnlyUsageRecordWithoutTransactionsOrInsertId(): void {
		$queries = [];
		$database = $this->createMock(IDatabase::class);
		$logger = $this->createMock(ILogger::class);

		$database->expects($this->once())->method('connect');
		$database->expects($this->never())->method('beginTransaction');
		$database->expects($this->never())->method('commit');
		$database->expects($this->never())->method('rollback');
		$database->expects($this->never())->method('insertId');
		$database->expects($this->exactly(2))
			->method('nonQuery')
			->willReturnCallback(static function(string $query) use (&$queries): void {
				$queries[] = $query;
			});
		$database->expects($this->exactly(2))->method('isError')->willReturn(false);
		$database->method('escape')->willReturnCallback(
			static fn(string $value): string => addslashes($value)
		);
		$logger->expects($this->never())->method('error');

		$listener = new MissionBayAiUsageLogListener($database, $logger);
		$listener->onProviderRequestCompleted(new AiProviderRequestCompletedEvent(
			new AiResultMetadata(
				'chat',
				'openai',
				'gpt-test',
				'request-1',
				1700000000,
				125.5,
				'stop',
				new AiUsage(10, 4, 14, 2, 1, ['requests' => 1], ['tier' => 'standard']),
				['adapter' => 'openaichatmodel']
			),
			'openaichatmodel',
			1700000001
		));

		$this->assertCount(2, $queries);
		$this->assertStringContainsString('CREATE TABLE IF NOT EXISTS `base3_missionbay_ai_usage`', $queries[0]);
		$this->assertStringContainsString('INSERT INTO `base3_missionbay_ai_usage`', $queries[1]);
		$this->assertStringContainsString("'openai'", $queries[1]);
		$this->assertStringContainsString("'gpt-test'", $queries[1]);
		$this->assertStringContainsString("'request-1'", $queries[1]);
		$this->assertStringContainsString("\n\t\t\t\t10,\n\t\t\t\t4,\n\t\t\t\t14,\n", $queries[1]);
	}
}
