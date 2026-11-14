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

namespace Base3\MissionBay\Test\Resource;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Base3\CoreFoundation\Api\IClassMap;
use Base3\Core\Event\EventManager;
use Base3\MissionBay\Ai\AiProviderRequestEventDispatcher;
use Base3\MissionBay\Resource\OpenAiEmbeddingModelAgentResource;
use Base3\MissionBay\Api\IAgentConfigValueResolver;

#[CoversClass(\Base3\MissionBay\Resource\OpenAiEmbeddingModelAgentResource::class)]
class OpenAiEmbeddingModelAgentResourceTest extends TestCase {

	private function makeResolver(array $map = []): IAgentConfigValueResolver {
		return new OpenAiEmbeddingModelAgentResourceTestDouble1($map);
	}

	private function classMap(): IClassMap {
		return $this->createStub(IClassMap::class);
	}

	private function providerRequestEvents(): AiProviderRequestEventDispatcher {
		return new AiProviderRequestEventDispatcher(new EventManager());
	}

	public function testGetName(): void {
		$this->assertSame('openaiembeddingmodelagentresource', OpenAiEmbeddingModelAgentResource::getName());
	}

	public function testSetConfigResolvesDefaults(): void {
		$resolver = $this->makeResolver([]);
		$r = new OpenAiEmbeddingModelAgentResource($resolver, $this->classMap(), $this->providerRequestEvents(), 'e1');

		$r->setConfig([]);

		$opts = $r->getOptions();
		$this->assertSame('text-embedding-3-small', $opts['model']);
		$this->assertNull($opts['apikey']);
		$this->assertSame('https://api.openai.com/v1/embeddings', $opts['endpoint']);
	}

	public function testSetConfigResolvesValuesFromResolver(): void {
		$resolver = $this->makeResolver([
			'model_key' => 'text-embedding-3-large',
			'apikey_key' => 'sk-test',
			'endpoint_key' => 'https://example.com/v1/embeddings',
		]);

		$r = new OpenAiEmbeddingModelAgentResource($resolver, $this->classMap(), $this->providerRequestEvents(), 'e2');

		$r->setConfig([
			'model' => 'model_key',
			'apikey' => 'apikey_key',
			'endpoint' => 'endpoint_key',
		]);

		$opts = $r->getOptions();
		$this->assertSame('text-embedding-3-large', $opts['model']);
		$this->assertSame('sk-test', $opts['apikey']);
		$this->assertSame('https://example.com/v1/embeddings', $opts['endpoint']);
	}

	public function testSetOptionsMerges(): void {
		$resolver = $this->makeResolver([]);
		$r = new OpenAiEmbeddingModelAgentResource($resolver, $this->classMap(), $this->providerRequestEvents(), 'e3');
		$r->setConfig([
			'model' => 'text-embedding-3-small',
			'apikey' => 'sk-test',
		]);

		$r->setOptions([
			'model' => 'text-embedding-3-large',
			'extra' => 'x',
		]);

		$opts = $r->getOptions();
		$this->assertSame('text-embedding-3-large', $opts['model']);
		$this->assertSame('sk-test', $opts['apikey']);
		$this->assertSame('x', $opts['extra']);
	}

	public function testEmbedReturnsEmptyArrayForEmptyInput(): void {
		$resolver = $this->makeResolver([]);
		$r = new OpenAiEmbeddingModelAgentResource($resolver, $this->classMap(), $this->providerRequestEvents(), 'e4');
		$r->setConfig(['apikey' => 'sk-test']);

		$this->assertSame([], $r->embed([]));
	}

	public function testEmbedThrowsIfApiKeyMissing(): void {
		$resolver = $this->makeResolver([]);
		$r = new OpenAiEmbeddingModelAgentResource($resolver, $this->classMap(), $this->providerRequestEvents(), 'e5');
		$r->setConfig([]); // apikey resolves to null

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Unable to resolve provider "openaitransport"');

		$r->embed(['hello']);
	}

	public function testEmbedMapsEmbeddingsFromResponse(): void {
		$resolver = $this->makeResolver([]);

		$r = new OpenAiEmbeddingModelAgentResourceTestDouble2($resolver, $this->classMap(), $this->providerRequestEvents(), 'e6');

		$r->setConfig(['apikey' => 'dummy']);

		$out = $r->embed(['a', 'b']);
		$this->assertSame([[0.1, 0.2], [0.3, 0.4]], $out);
	}

	public function testEmbedThrowsOnMalformedResponseShape(): void {
		// We avoid hitting curl: override embed to simulate "decoded JSON" but with malformed structure,
		// then assert the same exception message as the production code would throw.
		$resolver = $this->makeResolver([]);

		$r = new OpenAiEmbeddingModelAgentResourceTestDouble3($resolver, $this->classMap(), $this->providerRequestEvents(), 'e7');

		$r->setConfig(['apikey' => 'dummy']);

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Malformed OpenAI embedding response.');

		$r->embed(['x']);
	}
}

require_once __DIR__ . '/OpenAiEmbeddingModelAgentResourceTestDouble1.php';

require_once __DIR__ . '/OpenAiEmbeddingModelAgentResourceTestDouble2.php';

require_once __DIR__ . '/OpenAiEmbeddingModelAgentResourceTestDouble3.php';
