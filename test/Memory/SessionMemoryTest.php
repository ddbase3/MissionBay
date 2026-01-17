<?php declare(strict_types=1);

namespace MissionBay\Test\Memory;

use Base3\Session\Api\ISession;
use MissionBay\Memory\SessionMemory;
use PHPUnit\Framework\TestCase;

class SessionMemoryTest extends TestCase {

	private array $sessionBackup = [];

	protected function setUp(): void {
		parent::setUp();

		$this->sessionBackup = $_SESSION ?? [];
		$_SESSION = [];
	}

	protected function tearDown(): void {
		$_SESSION = $this->sessionBackup;

		parent::tearDown();
	}

	private function makeSession(bool $started, string $id = 'sess1'): ISession {
		return new class($started, $id) implements ISession {

			private bool $started;
			private string $id;

			public function __construct(bool $started, string $id) {
				$this->started = $started;
				$this->id = $id;
			}

			public function started(): bool {
				return $this->started;
			}

			public function getId(): string {
				return $this->id;
			}

			public function start(): bool {
				$this->started = true;
				return true;
			}

			public function destroy(): bool {
				$this->started = false;
				return true;
			}

			public function get(string $key, mixed $default = null): mixed {
				return $default;
			}

			public function set(string $key, mixed $value): void {
				// no-op for this test
			}

			public function has(string $key): bool {
				return false;
			}

			public function remove(string $key): void {
				// no-op for this test
			}
		};
	}

	public function testGetName(): void {
		$this->assertSame('sessionmemory', SessionMemory::getName());
	}

	public function testLoadNodeHistoryReturnsEmptyWhenSessionNotStartedAndDoesNotCreateSessionData(): void {
		$mem = new SessionMemory($this->makeSession(false));

		$this->assertSame([], $mem->loadNodeHistory('n1'));
		$this->assertArrayNotHasKey('mb_memory', $_SESSION);
	}

	public function testAppendNodeHistoryDoesNothingWhenSessionNotStarted(): void {
		$mem = new SessionMemory($this->makeSession(false));

		$mem->appendNodeHistory('n1', ['id' => 'm1', 'content' => 'x']);

		$this->assertArrayNotHasKey('mb_memory', $_SESSION);
		$this->assertSame([], $mem->loadNodeHistory('n1'));
	}

	public function testEnsureInitializesMemoryStructureWhenSessionStarted(): void {
		$mem = new SessionMemory($this->makeSession(true));

		$this->assertSame([], $mem->loadNodeHistory('n1'));

		$this->assertArrayHasKey('mb_memory', $_SESSION);
		$this->assertIsArray($_SESSION['mb_memory']);
		$this->assertIsArray($_SESSION['mb_memory']['nodes'] ?? null);
		$this->assertIsArray($_SESSION['mb_memory']['data'] ?? null);
	}

	public function testAppendAndLoadStoresMessagesPerNodeWhenStarted(): void {
		$mem = new SessionMemory($this->makeSession(true));

		$mem->appendNodeHistory('n1', ['id' => 'm1', 'content' => 'a']);
		$mem->appendNodeHistory('n1', ['id' => 'm2', 'content' => 'b']);
		$mem->appendNodeHistory('n2', ['id' => 'm3', 'content' => 'c']);

		$this->assertSame(
			[
				['id' => 'm1', 'content' => 'a'],
				['id' => 'm2', 'content' => 'b'],
			],
			$mem->loadNodeHistory('n1')
		);

		$this->assertSame(
			[
				['id' => 'm3', 'content' => 'c'],
			],
			$mem->loadNodeHistory('n2')
		);
	}

	public function testAppendEnforcesMaxLimitByKeepingLatestMessages(): void {
		$mem = new SessionMemory($this->makeSession(true));

		for ($i = 1; $i <= 25; $i++) {
			$mem->appendNodeHistory('n1', ['id' => 'm' . $i]);
		}

		$hist = $mem->loadNodeHistory('n1');
		$this->assertCount(20, $hist);

		$this->assertSame('m6', $hist[0]['id']);
		$this->assertSame('m25', $hist[19]['id']);
	}

	public function testSetFeedbackReturnsFalseWhenSessionNotStarted(): void {
		$mem = new SessionMemory($this->makeSession(false));

		$this->assertFalse($mem->setFeedback('n1', 'm1', 'up'));
		$this->assertArrayNotHasKey('mb_memory', $_SESSION);
	}

	public function testSetFeedbackReturnsFalseIfNodeMissing(): void {
		$mem = new SessionMemory($this->makeSession(true));

		$this->assertFalse($mem->setFeedback('n1', 'm1', 'up'));
	}

	public function testSetFeedbackReturnsFalseIfMessageIdNotFound(): void {
		$mem = new SessionMemory($this->makeSession(true));

		$mem->appendNodeHistory('n1', ['id' => 'm1', 'content' => 'x']);

		$this->assertFalse($mem->setFeedback('n1', 'does-not-exist', 'up'));
	}

	public function testSetFeedbackSetsFeedbackAndReturnsTrueWhenMessageFound(): void {
		$mem = new SessionMemory($this->makeSession(true));

		$mem->appendNodeHistory('n1', ['id' => 'm1', 'content' => 'x']);
		$this->assertTrue($mem->setFeedback('n1', 'm1', 'up'));

		$hist = $mem->loadNodeHistory('n1');
		$this->assertSame('up', $hist[0]['feedback'] ?? null);
	}

	public function testResetNodeHistoryDoesNothingWhenSessionNotStarted(): void {
		$mem = new SessionMemory($this->makeSession(false));

		$mem->resetNodeHistory('n1');

		$this->assertArrayNotHasKey('mb_memory', $_SESSION);
	}

	public function testResetNodeHistoryClearsHistoryForNode(): void {
		$mem = new SessionMemory($this->makeSession(true));

		$mem->appendNodeHistory('n1', ['id' => 'm1']);
		$mem->appendNodeHistory('n2', ['id' => 'm2']);

		$mem->resetNodeHistory('n1');

		$this->assertSame([], $mem->loadNodeHistory('n1'));
		$this->assertSame([['id' => 'm2']], $mem->loadNodeHistory('n2'));
	}

	public function testGetPriorityReturnsZero(): void {
		$mem = new SessionMemory($this->makeSession(true));
		$this->assertSame(0, $mem->getPriority());
	}
}
