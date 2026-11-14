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

use PHPUnit\Framework\TestCase;
use Base3\MissionBay\Agent\AgentConfigValueResolver;
use Base3\CoreFoundation\ConfigValue\Api\IConfigValueModeResolver;
use Base3\Core\ConfigValue\Mode\ConfigurationConfigValueModeResolver;
use Base3\Core\ConfigValue\Mode\EnvConfigValueModeResolver;
use Base3\Core\ConfigValue\Mode\FixedConfigValueModeResolver;
use Base3\Core\ConfigValue\Resolver\ConfigValueResolver;
use Base3\Test\Core\ClassMapStub;
use Base3\Test\Configuration\ConfigurationStub;

final class AgentConfigValueResolverTest extends TestCase {

	private function makeResolver(array $root = []): AgentConfigValueResolver {
		$classMap = new ClassMapStub();
		foreach ([
			new FixedConfigValueModeResolver(),
			new EnvConfigValueModeResolver(),
			new ConfigurationConfigValueModeResolver(new ConfigurationStub($root))
		] as $modeResolver) {
			$classMap->registerInstance($modeResolver, interfaces: [IConfigValueModeResolver::class]);
		}

		return new AgentConfigValueResolver(new ConfigValueResolver($classMap));
	}

	public function testResolveValueReturnsScalarUnchanged(): void {
		$r = $this->makeResolver();

		$this->assertSame(null, $r->resolveValue(null));
		$this->assertSame(true, $r->resolveValue(true));
		$this->assertSame(false, $r->resolveValue(false));
		$this->assertSame(123, $r->resolveValue(123));
		$this->assertSame(1.5, $r->resolveValue(1.5));
		$this->assertSame('x', $r->resolveValue('x'));
	}

	public function testFixedAndDefaultReturnValue(): void {
		$r = $this->makeResolver();

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
		$r = $this->makeResolver();

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
		$r = $this->makeResolver([
			'openai' => ['apikey' => 'k1', 'model' => 'gpt']
		]);

		$this->assertSame('k1', $r->resolveValue([
			'mode' => 'config',
			'section' => 'openai',
			'key' => 'apikey'
		]));
	}

	public function testConfigThrowsIfSectionOrKeyMissing(): void {
		$r = $this->makeResolver([
			'a' => ['b' => 'c']
		]);

		$this->expectException(\RuntimeException::class);
		$r->resolveValue([
			'mode' => 'config',
			'section' => 'a'
		]);
	}

	public function testConfigThrowsIfSectionNotArray(): void {
		$r = $this->makeResolver([
			'sec' => 'nope'
		]);

		$this->expectException(\RuntimeException::class);
		$r->resolveValue([
			'mode' => 'config',
			'section' => 'sec',
			'key' => 'x'
		]);
	}

	public function testConfigThrowsIfKeyMissing(): void {
		$r = $this->makeResolver([
			'sec' => ['a' => 1]
		]);

		$this->expectException(\RuntimeException::class);
		$r->resolveValue([
			'mode' => 'config',
			'section' => 'sec',
			'key' => 'missing'
		]);
	}

	public function testRandomReturnsNullForInvalidOrEmptyValue(): void {
		$r = $this->makeResolver();

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
		$r = $this->makeResolver();

		// deterministic: only one possible choice
		$this->assertSame('x', $r->resolveValue([
			'mode' => 'random',
			'value' => ['x']
		]));
	}

	public function testUuidReturnsUuidV4String(): void {
		$r = $this->makeResolver();

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
		$r = $this->makeResolver();

		$this->assertNull($r->resolveValue(['mode' => 'inherit']));
		$this->assertSame([], $r->resolveValue([]));

		$this->expectException(\RuntimeException::class);
		$r->resolveValue(['mode' => 'something_else']);
	}
}
