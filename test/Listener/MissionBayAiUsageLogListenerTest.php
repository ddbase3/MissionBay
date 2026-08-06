<?php declare(strict_types=1);

namespace MissionBay\Test\Listener;

use AssistantFoundation\Dto\AiResultMetadata;
use AssistantFoundation\Dto\AiUsage;
use AssistantFoundation\Event\AiProviderRequestCompletedEvent;
use Base3\Api\IRequest;
use Base3\Database\Api\IDatabase;
use Base3\Logger\Api\ILogger;
use Base3\Usermanager\Api\IUsermanager;
use MissionBay\Listener\MissionBayAiUsageLogListener;
use PHPUnit\Framework\TestCase;

final class MissionBayAiUsageLogListenerTest extends TestCase {

	public function testStoresOneAppendOnlyUsageRecordWithoutTransactionsOrInsertId(): void {
		$queries = [];
		$database = $this->createMock(IDatabase::class);
		$logger = $this->createMock(ILogger::class);
		$usermanager = $this->createMock(IUsermanager::class);
		$request = $this->createMock(IRequest::class);

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
		$usermanager->expects($this->once())->method('getUser')->willReturn([
			'id' => 42,
			'login' => 'test.user'
		]);
		$request->expects($this->once())->method('getContext')->willReturn(IRequest::CONTEXT_WEB_API);

		$listener = new MissionBayAiUsageLogListener($database, $logger, $usermanager, $request);
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
		$this->assertStringContainsString('`user_id` INT NOT NULL DEFAULT 0', $queries[0]);
		$this->assertStringContainsString('`user_login` VARCHAR(191) NOT NULL DEFAULT \'unknown_user\'', $queries[0]);
		$this->assertStringContainsString('`request_context` VARCHAR(32) NOT NULL DEFAULT \'unknown\'', $queries[0]);
		$this->assertStringContainsString('KEY `idx_ai_usage_user_time` (`user_id`, `occurred_at`)', $queries[0]);
		$this->assertStringContainsString('INSERT INTO `base3_missionbay_ai_usage`', $queries[1]);
		$this->assertStringContainsString("'openai'", $queries[1]);
		$this->assertStringContainsString("'gpt-test'", $queries[1]);
		$this->assertStringContainsString("'request-1'", $queries[1]);
		$this->assertStringContainsString("\n\t\t\t\t42,\n\t\t\t\t'test.user',\n\t\t\t\t'web_api',\n", $queries[1]);
		$this->assertStringContainsString("\n\t\t\t\t10,\n\t\t\t\t4,\n\t\t\t\t14,\n", $queries[1]);
	}

	public function testStoresImageTokenUsageInTheSameAiUsageTable(): void {
		$queries = [];
		$database = $this->createMock(IDatabase::class);
		$logger = $this->createMock(ILogger::class);
		$usermanager = $this->createMock(IUsermanager::class);
		$request = $this->createMock(IRequest::class);

		$database->expects($this->once())->method('connect');
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
		$usermanager->method('getUser')->willReturn(null);
		$request->method('getContext')->willReturn(IRequest::CONTEXT_CRON);

		$listener = new MissionBayAiUsageLogListener($database, $logger, $usermanager, $request);
		$listener->onProviderRequestCompleted(new AiProviderRequestCompletedEvent(
			new AiResultMetadata(
				'image',
				'mistraltransport',
				'mistral-small-latest',
				'image-request-1',
				1700000000,
				850.0,
				'stop',
				new AiUsage(21, 9, 30, null, null, [
					'input_prompts' => 1,
					'output_images' => 1
				]),
				['adapter' => 'mistralimagemodel']
			),
			'mistralimagemodel',
			1700000001
		));

		$this->assertCount(2, $queries);
		$this->assertStringContainsString('CREATE TABLE IF NOT EXISTS `base3_missionbay_ai_usage`', $queries[0]);
		$this->assertStringContainsString('INSERT INTO `base3_missionbay_ai_usage`', $queries[1]);
		$this->assertStringContainsString("'image'", $queries[1]);
		$this->assertStringContainsString("'mistraltransport'", $queries[1]);
		$this->assertStringContainsString("'mistral-small-latest'", $queries[1]);
		$this->assertStringContainsString("\n\t\t\t\t21,\n\t\t\t\t9,\n\t\t\t\t30,\n", $queries[1]);
		$this->assertStringContainsString('output_images', $queries[1]);
	}

}
