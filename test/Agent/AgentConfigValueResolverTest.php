<?php declare(strict_types=1);

namespace MissionBay\Test\Agent;

use PHPUnit\Framework\TestCase;
use MissionBay\Agent\AgentConfigValueResolver;
use Base3\Configuration\Api\IConfiguration;
use Base3\Test\Configuration\ConfigurationStub;

final class AgentConfigValueResolverTest extends TestCase {

	private function makeConfig(array $root = []): IConfiguration {
		return new ConfigurationStub($root);
	}

	public function testResolveValueReturnsScalarUnchanged(): void {
		$r = new AgentConfigValueResolver($this->makeConfig());

		$this->assertSame(null, $r->resolveValue(null));
		$this->assertSame(true, $r->resolveValue(true));
		$this->assertSame(false, $r->resolveValue(false));
		$this->assertSame(123, $r->resolveValue(123));
		$this->assertSame(1.5, $r->resolveValue(1.5));
		$this->assertSame('x', $r->resolveValue('x'));
	}

	public function testFixedAndDefaultReturnValue(): void {
		$r = new AgentConfigValueResolver($this->makeConfig());

		$this->assertSame('a', $r->resolveValue([
			'mode' => 'fixed',
			'value' => 'a'
		]));

		$this->assertSame(42, $r->resolveValue([
			'mode' => 'default',
			'value' => 42
		]));

		$this->assertNull($r->resolveValue([
			'mode' => 'fixed'
		]));
	}

	public function testEnvReturnsValueOrNull(): void {
		$r = new AgentConfigValueResolver($this->makeConfig());

		$var = 'MB_TEST_ENV_' . bin2hex(random_bytes(4));

		// ensure not set
		putenv($var);

		$this->assertNull($r->resolveValue([
			'mode' => 'env',
			'value' => $var
		]));

		putenv($var . '=hello');
		$this->assertSame('hello', $r->resolveValue([
			'mode' => 'env',
			'value' => $var
		]));

		// cleanup
		putenv($var);
	}

	public function testConfigResolvesFromConfiguration(): void {
		$config = $this->makeConfig([
			'openai' => [
				'apikey' => 'k1',
				'model' => 'gpt'
			]
		]);

		$r = new AgentConfigValueResolver($config);

		$this->assertSame('k1', $r->resolveValue([
			'mode' => 'config',
			'section' => 'openai',
			'key' => 'apikey'
		]));
	}

	public function testConfigThrowsIfSectionOrKeyMissing(): void {
		$r = new AgentConfigValueResolver($this->makeConfig([
			'a' => ['b' => 'c']
		]));

		$this->expectException(\RuntimeException::class);
		$r->resolveValue([
			'mode' => 'config',
			'section' => 'a'
		]);
	}

	public function testConfigThrowsIfSectionNotArray(): void {
		$r = new AgentConfigValueResolver($this->makeConfig([
			'sec' => 'nope'
		]));

		$this->expectException(\RuntimeException::class);
		$r->resolveValue([
			'mode' => 'config',
			'section' => 'sec',
			'key' => 'x'
		]);
	}

	public function testConfigThrowsIfKeyMissing(): void {
		$r = new AgentConfigValueResolver($this->makeConfig([
			'sec' => ['a' => 1]
		]));

		$this->expectException(\RuntimeException::class);
		$r->resolveValue([
			'mode' => 'config',
			'section' => 'sec',
			'key' => 'missing'
		]);
	}

	public function testRandomReturnsNullForInvalidOrEmptyValue(): void {
		$r = new AgentConfigValueResolver($this->makeConfig());

		$this->assertNull($r->resolveValue([
			'mode' => 'random'
		]));

		$this->assertNull($r->resolveValue([
			'mode' => 'random',
			'value' => []
		]));

		$this->assertNull($r->resolveValue([
			'mode' => 'random',
			'value' => 'not-array'
		]));
	}

	public function testRandomReturnsOneElementFromArray(): void {
		$r = new AgentConfigValueResolver($this->makeConfig());

		// deterministic: only one possible choice
		$this->assertSame('x', $r->resolveValue([
			'mode' => 'random',
			'value' => ['x']
		]));
	}

	public function testUuidReturnsUuidV4String(): void {
		$r = new AgentConfigValueResolver($this->makeConfig());

		$u1 = $r->resolveValue(['mode' => 'uuid']);
		$u2 = $r->resolveValue(['mode' => 'uuid']);

		$this->assertIsString($u1);
		$this->assertMatchesRegularExpression(
			'/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
			$u1
		);

		// very high probability of being different
		$this->assertNotSame($u1, $u2);
	}

	public function testInheritAndUnknownModeReturnNull(): void {
		$r = new AgentConfigValueResolver($this->makeConfig());

		$this->assertNull($r->resolveValue(['mode' => 'inherit']));
		$this->assertNull($r->resolveValue(['mode' => 'something_else']));
		$this->assertNull($r->resolveValue([])); // defaults to inherit
	}
}
