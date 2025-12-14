<?php declare(strict_types=1);

namespace MissionBay\Resource\Test;

use PHPUnit\Framework\TestCase;
use MissionBay\Resource\CurrentTimeAgentTool;
use MissionBay\Api\IAgentTool;
use MissionBay\Api\IAgentContext;
use MissionBay\Api\IAgentMemory;

class CurrentTimeAgentToolTest extends TestCase {

	public function testImplementsAgentToolInterface(): void {
		$tool = new CurrentTimeAgentTool('id1');
		$this->assertInstanceOf(IAgentTool::class, $tool);
	}

	public function testGetNameAndDescription(): void {
		$tool = new CurrentTimeAgentTool('id1');

		$this->assertSame('currenttimeagenttool', CurrentTimeAgentTool::getName());
		$this->assertSame(
			'Provides the current server time and timezone. Useful for scheduling and answering time-related questions.',
			$tool->getDescription()
		);
	}

	public function testGetToolDefinitionsReturnsExpectedSchema(): void {
		$tool = new CurrentTimeAgentTool('id1');

		$defs = $tool->getToolDefinitions();
		$this->assertIsArray($defs);
		$this->assertCount(1, $defs);

		$def = $defs[0];
		$this->assertSame('function', $def['type']);
		$this->assertSame('Current Time Lookup', $def['label']);
		$this->assertSame('get_current_time', $def['function']['name']);
	}

	public function testCallToolThrowsForUnsupportedToolName(): void {
		$tool = new CurrentTimeAgentTool('id1');
		$context = new CurrentTimeAgentContextStub();

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Unsupported tool: nope');

		$tool->callTool('nope', [], $context);
	}

	public function testCallToolUsesProvidedValidTimezone(): void {
		$tool = new CurrentTimeAgentTool('id1');
		$context = new CurrentTimeAgentContextStub();

		$result = $tool->callTool('get_current_time', ['timezone' => 'Europe/Berlin'], $context);

		$this->assertSame('Europe/Berlin', $result['timezone']);
		$this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $result['time']);
	}

	public function testCallToolDefaultsToServerTimezoneWhenTimezoneArgumentMissing(): void {
		$tool = new CurrentTimeAgentTool('id1');
		$context = new CurrentTimeAgentContextStub();

		$serverTz = date_default_timezone_get();
		$result = $tool->callTool('get_current_time', [], $context);

		$this->assertSame($serverTz, $result['timezone']);
	}

	public function testCallToolFallsBackToServerTimezoneOnInvalidTimezoneInIsolatedProcess(): void {
		$serverTz = date_default_timezone_get();
		$cwd = getcwd();

		$code = <<<'PHP'
<?php declare(strict_types=1);

function findProjectRoot(string $start): ?string {
	$dir = $start;
	for ($i = 0; $i < 15; $i++) {
		if (is_file($dir . '/src/Api/IBase.php') && is_file($dir . '/plugin/MissionBay/src/Resource/CurrentTimeAgentTool.php')) {
			return $dir;
		}
		$parent = dirname($dir);
		if ($parent === $dir) break;
		$dir = $parent;
	}
	return null;
}

$start = getenv('CT_CWD') ?: getcwd();
$root = findProjectRoot($start);

if (!$root) {
	fwrite(STDERR, "Cannot find project root from: $start\n");
	exit(3);
}

$require = function (string $path) use ($root) {
	$full = $root . '/' . ltrim($path, '/');
	if (!is_file($full)) {
		fwrite(STDERR, "Missing required file: $full\n");
		exit(4);
	}
	require_once $full;
};

// Base3 IBase (your real path)
$require('src/Api/IBase.php');

// MissionBay API interfaces used by AbstractAgentResource / tool signatures
$require('plugin/MissionBay/src/Api/IAgentMemory.php');
$require('plugin/MissionBay/src/Api/IAgentContext.php');
$require('plugin/MissionBay/src/Api/IAgentResource.php');
$require('plugin/MissionBay/src/Api/IAgentTool.php');

// DTO/class referenced by AbstractAgentResource return type
$require('plugin/MissionBay/src/Agent/AgentNodeDock.php');

// Abstract base + the tool
$require('plugin/MissionBay/src/Resource/AbstractAgentResource.php');
$require('plugin/MissionBay/src/Resource/CurrentTimeAgentTool.php');

if (!class_exists(\MissionBay\Resource\CurrentTimeAgentTool::class)) {
	fwrite(STDERR, "Failed to load CurrentTimeAgentTool\n");
	exit(5);
}

// Minimal context implementation
$ctx = new class implements \MissionBay\Api\IAgentContext {
	public static function getName(): string { return 'ct_subprocess_context'; }

	public function getMemory(): \MissionBay\Api\IAgentMemory {
		return new class implements \MissionBay\Api\IAgentMemory {
			public static function getName(): string { return 'ct_subprocess_memory'; }
			public function loadNodeHistory(string $nodeId): array { return []; }
			public function appendNodeHistory(string $nodeId, array $message): void {}
			public function setFeedback(string $nodeId, string $messageId, ?string $feedback): bool { return false; }
			public function resetNodeHistory(string $nodeId): void {}
			public function getPriority(): int { return 0; }
		};
	}

	public function setMemory(\MissionBay\Api\IAgentMemory $memory): void {}
	public function setVar(string $key, mixed $value): void {}
	public function getVar(string $key): mixed { return null; }
	public function forgetVar(string $key): void {}
	public function listVars(): array { return []; }
};

$tool = new \MissionBay\Resource\CurrentTimeAgentTool('id1');
$result = $tool->callTool('get_current_time', ['timezone' => 'This/Is-Not-A-Timezone'], $ctx);

echo json_encode($result, JSON_UNESCAPED_SLASHES);
PHP;

		$tmp = tempnam(sys_get_temp_dir(), 'cttz_');
		if ($tmp === false) {
			$this->fail('Failed to create temp file for subprocess test.');
		}
		file_put_contents($tmp, $code);

		$cmd = [
			PHP_BINARY,
			'-d', 'xdebug.mode=off',
			'-d', 'xdebug.start_with_request=no',
			'-d', 'xdebug.default_enable=0',
			$tmp
		];

		$descriptors = [
			0 => ['pipe', 'r'],
			1 => ['pipe', 'w'],
			2 => ['pipe', 'w'],
		];

		$env = $_ENV;
		$env['CT_CWD'] = $cwd;

		$proc = proc_open($cmd, $descriptors, $pipes, $cwd, $env);
		if (!is_resource($proc)) {
			@unlink($tmp);
			$this->fail('Failed to start subprocess.');
		}

		fclose($pipes[0]);
		$stdout = stream_get_contents($pipes[1]);
		$stderr = stream_get_contents($pipes[2]);
		fclose($pipes[1]);
		fclose($pipes[2]);

		$exit = proc_close($proc);
		@unlink($tmp);

		$this->assertSame(0, $exit, "Subprocess failed. STDERR:\n" . $stderr);

		$data = json_decode((string)$stdout, true);
		$this->assertIsArray($data, 'Subprocess did not return valid JSON: ' . $stdout);

		$this->assertSame($serverTz, $data['timezone'] ?? null);
		$this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string)($data['time'] ?? ''));
	}

}

class CurrentTimeAgentContextStub implements IAgentContext {

	private array $vars = [];
	private IAgentMemory $memory;

	public function __construct() {
		$this->memory = new CurrentTimeAgentMemoryStub();
	}

	public static function getName(): string {
		return 'currenttimeagentcontextstub';
	}

	public function getMemory(): IAgentMemory { return $this->memory; }
	public function setMemory(IAgentMemory $memory): void { $this->memory = $memory; }

	public function setVar(string $key, mixed $value): void { $this->vars[$key] = $value; }
	public function getVar(string $key): mixed { return $this->vars[$key] ?? null; }
	public function forgetVar(string $key): void { unset($this->vars[$key]); }
	public function listVars(): array { return array_keys($this->vars); }

}

class CurrentTimeAgentMemoryStub implements IAgentMemory {

	public static function getName(): string {
		return 'currenttimeagentmemorystub';
	}

	public function loadNodeHistory(string $nodeId): array { return []; }
	public function appendNodeHistory(string $nodeId, array $message): void {}
	public function setFeedback(string $nodeId, string $messageId, ?string $feedback): bool { return false; }
	public function resetNodeHistory(string $nodeId): void {}
	public function getPriority(): int { return 0; }

}
