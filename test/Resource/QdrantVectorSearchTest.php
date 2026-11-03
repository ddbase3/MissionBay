<?php declare(strict_types=1);

namespace MissionBay\Resource;

/**
 * Guarded cURL mocks for MissionBay\Resource\*
 * (avoid "Cannot redeclare curl_init()" regardless of load order)
 */
if (!class_exists(FakeCurlState::class, false)) {
	
}

if (!function_exists(__NAMESPACE__ . '\curl_init')) {
	function curl_init($url) {
		return (object)['endpoint' => $url];
	}
}

if (!function_exists(__NAMESPACE__ . '\curl_setopt')) {
	function curl_setopt($ch, $option, $value) {
		FakeCurlState::setOpt((int)$option, $value);
		return true;
	}
}

if (!function_exists(__NAMESPACE__ . '\curl_exec')) {
	function curl_exec($ch) {
		return FakeCurlState::$execResult;
	}
}

if (!function_exists(__NAMESPACE__ . '\curl_errno')) {
	function curl_errno($ch) {
		return FakeCurlState::$errno;
	}
}

if (!function_exists(__NAMESPACE__ . '\curl_error')) {
	function curl_error($ch) {
		return FakeCurlState::$error;
	}
}

if (!function_exists(__NAMESPACE__ . '\curl_getinfo')) {
	function curl_getinfo($ch, $opt) {
		if ($opt === \CURLINFO_HTTP_CODE) {
			return FakeCurlState::$httpCode;
		}
		return null;
	}
}

if (!function_exists(__NAMESPACE__ . '\curl_close')) {
	function curl_close($ch) {
		return;
	}
}


namespace Test\Resource;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use MissionBay\Resource\QdrantVectorSearch;
use MissionBay\Resource\FakeCurlState;
use MissionBay\Api\IAgentConfigValueResolver;

#[CoversClass(\MissionBay\Resource\QdrantVectorSearch::class)]
class QdrantVectorSearchTest extends TestCase {

	protected function setUp(): void {
		FakeCurlState::reset();
	}

	private function makeResolver(array $map = []): IAgentConfigValueResolver {
		return new QdrantVectorSearchTestDouble1($map);
	}

	public function testGetNameAndDescription(): void {
		$r = new QdrantVectorSearch($this->makeResolver([]), 'id1');
		$this->assertSame('qdrantvectorsearch', QdrantVectorSearch::getName());
		$this->assertSame('Performs similarity search in a Qdrant collection.', $r->getDescription());
	}

	public function testSearchThrowsWhenNotConfigured(): void {
		$r = new QdrantVectorSearch($this->makeResolver([]), 'id2');
		$r->setConfig([]); // endpoint/collection missing => should throw

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('endpoint or collection not configured');

		$r->search([0.1, 0.2], 3, null);
	}

	public function testSearchFiltersByMinScoreAndMapsFields(): void {
		$resolver = $this->makeResolver([
			'endpoint_key' => 'https://qdrant.example',
			'apikey_key' => 'k',
			'collection_key' => 'col',
		]);

		$r = new QdrantVectorSearch($resolver, 'id3');
		$r->setConfig([
			'endpoint' => 'endpoint_key',
			'apikey' => 'apikey_key',
			'collection' => 'collection_key',
		]);

		// 3 hits, one below minScore => expect 2
		FakeCurlState::$execResult = json_encode([
			'result' => [
				['id' => 'a', 'score' => 0.9, 'payload' => ['x' => 1]],
				['id' => 'b', 'score' => 0.6, 'payload' => ['x' => 2]],
				['id' => 'c', 'score' => 0.1, 'payload' => ['x' => 3]],
			]
		]);

		$out = $r->search([1.0, 2.0], 3, 0.5);

		$this->assertCount(2, $out);

		$this->assertSame('a', $out[0]['id']);
		$this->assertSame(0.9, $out[0]['score']);
		$this->assertSame(['x' => 1], $out[0]['payload']);

		$this->assertSame('b', $out[1]['id']);
		$this->assertSame(0.6, $out[1]['score']);
		$this->assertSame(['x' => 2], $out[1]['payload']);
	}
}

require_once __DIR__ . '/FakeCurlState.php';

require_once __DIR__ . '/QdrantVectorSearchTestDouble1.php';
