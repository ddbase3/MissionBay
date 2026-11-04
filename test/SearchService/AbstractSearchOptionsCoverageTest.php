<?php declare(strict_types=1);

namespace MissionBay\Test\SearchService;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/AbstractSearchOptionsProbe.php';

final class AbstractSearchOptionsCoverageTest extends TestCase {

	private function service(): AbstractSearchOptionsProbe {
		return new AbstractSearchOptionsProbe();
	}

	public function testIdentityUsesTheDeclaredTestServiceName(): void {
		$this->assertSame('abstractsearchoptionsprobe', AbstractSearchOptionsProbe::getName());
	}

	public function testOptionsMergeWithoutDroppingExistingValues(): void {
		$service = $this->service();
		$service->setOptions(['apikey' => 'old', 'timeout_seconds' => 10]);
		$service->setOptions(['apikey' => 'new']);
		$this->assertSame(['apikey' => 'new', 'timeout_seconds' => 10], $service->getOptions());
	}

	public function testEmptyModelFallsBackToDefault(): void {
		$this->assertSame('search-default', $this->service()->model(['model' => '  ']));
	}

	public function testConfiguredModelOverridesDefault(): void {
		$this->assertSame('custom', $this->service()->model(['model' => ' custom ']));
	}

	public function testEmptyEndpointFallsBackToDefault(): void {
		$this->assertSame('https://example.test/search', $this->service()->endpoint(['endpoint' => '  ']));
	}

	public function testConfiguredEndpointOverridesDefault(): void {
		$this->assertSame('https://host.test/api', $this->service()->endpoint(['endpoint' => ' https://host.test/api ']));
	}

	public function testMissingApiKeyIsRejectedForBearerAuth(): void {
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Missing API key');
		$this->service()->apiKey([]);
	}

	public function testNoAuthAllowsEmptyApiKey(): void {
		$this->assertSame('', $this->service()->apiKey(['auth_type' => 'none']));
	}

	public function testNoneAuthIsCaseInsensitive(): void {
		$this->assertSame('', $this->service()->apiKey(['auth_type' => ' NONE ']));
	}

	public function testConfiguredApiKeyIsTrimmed(): void {
		$this->assertSame('key', $this->service()->apiKey(['apikey' => ' key ']));
	}

	public function testEmptyApiKeyStillFailsForCustomAuth(): void {
		$this->expectException(\RuntimeException::class);
		$this->service()->apiKey(['auth_type' => 'header']);
	}

	public function testMissingIntegerUsesDefault(): void {
		$this->assertSame(40, $this->service()->integer([], 'timeout', 40));
	}

	public function testNumericStringCanSetPositiveInteger(): void {
		$this->assertSame(27, $this->service()->integer(['timeout' => '27'], 'timeout', 40));
	}

	public function testNegativeIntegerUsesDefault(): void {
		$this->assertSame(40, $this->service()->integer(['timeout' => -2], 'timeout', 40));
	}

	public function testZeroIntegerUsesDefault(): void {
		$this->assertSame(40, $this->service()->integer(['timeout' => 0], 'timeout', 40));
	}

	public function testNonnumericIntegerUsesDefault(): void {
		$this->assertSame(40, $this->service()->integer(['timeout' => 'later'], 'timeout', 40));
	}

	public function testMissingBoolUsesDefault(): void {
		$this->assertTrue($this->service()->boolean([], 'enabled', true));
		$this->assertFalse($this->service()->boolean([], 'enabled', false));
	}

	public function testBooleanInputIsKept(): void {
		$this->assertFalse($this->service()->boolean(['enabled' => false], 'enabled', true));
	}

	public function testNonzeroIntegerIsTrue(): void {
		$this->assertTrue($this->service()->boolean(['enabled' => 3], 'enabled', false));
	}

	public function testZeroIntegerIsFalse(): void {
		$this->assertFalse($this->service()->boolean(['enabled' => 0], 'enabled', true));
	}

	public function testTrueStringsAreRecognized(): void {
		foreach (['yes', 'ON', 'TRUE', '1'] as $value) {
			$this->assertTrue($this->service()->boolean(['enabled' => $value], 'enabled', false));
		}
	}

	public function testFalseStringsAreRecognized(): void {
		foreach (['no', 'OFF', 'FALSE', '0'] as $value) {
			$this->assertFalse($this->service()->boolean(['enabled' => $value], 'enabled', true));
		}
	}

	public function testUnknownBoolUsesDefault(): void {
		$this->assertTrue($this->service()->boolean(['enabled' => 'maybe'], 'enabled', true));
		$this->assertFalse($this->service()->boolean(['enabled' => 'maybe'], 'enabled', false));
	}

	public function testTimeoutOptionsHaveIndependentDefaults(): void {
		$this->assertSame(['timeout' => 120, 'connect_timeout' => 15], $this->service()->requestOptions([]));
	}

	public function testTimeoutOptionsUsePositiveConfiguredValues(): void {
		$this->assertSame(['timeout' => 8, 'connect_timeout' => 4], $this->service()->requestOptions(['timeout_seconds' => 8, 'connect_timeout_seconds' => 4]));
	}

	public function testTimeoutOptionsRejectZeroValues(): void {
		$this->assertSame(['timeout' => 120, 'connect_timeout' => 15], $this->service()->requestOptions(['timeout_seconds' => 0, 'connect_timeout_seconds' => -1]));
	}

	public function testStringListSplitsCommasAndLines(): void {
		$this->assertSame(['one', 'two', 'three'], $this->service()->stringList(['fields' => "one,two\nthree"], 'fields'));
	}

	public function testStringListDeduplicatesAndTrims(): void {
		$this->assertSame(['one', 'two'], $this->service()->stringList(['fields' => [' one ', '', 'two', 'one']], 'fields'));
	}

	public function testStringListDoesNotTreatNumericInputAsList(): void {
		$this->assertSame([], $this->service()->stringList(['fields' => 1], 'fields'));
	}

	public function testCollectStringsRecursivelyIncludesOnlyValues(): void {
		$this->assertSame(['one', 'two', 'three'], $this->service()->recursiveStrings(['key' => 'one', ['two', ['three']], 123, false]));
	}

	public function testCollectStringsRecursivelyIgnoresNonarrays(): void {
		$this->assertSame([], $this->service()->recursiveStrings(new \stdClass()));
	}

	public function testCollectStringsRecursivelyReturnsScalarString(): void {
		$this->assertSame(['hello'], $this->service()->recursiveStrings('hello'));
	}

	public function testCollectUrlsRecursivelyKeepsNestedLinks(): void {
		$urls = $this->service()->recursiveUrls(['items' => [['url' => 'https://one.test', 'title' => 'One'], ['extra' => ['url' => 'https://two.test', 'name' => 'Two', 'text' => 'Snippet']]]]);
		$this->assertCount(2, $urls);
		$this->assertSame('One', $urls[0]['title']);
		$this->assertSame('Two', $urls[1]['title']);
		$this->assertSame('Snippet', $urls[1]['snippet']);
	}

	public function testCollectUrlsUsesSourceAndSnippetFields(): void {
		$urls = $this->service()->recursiveUrls(['url' => 'https://example.test', 'source' => 'docs', 'snippet' => 'summary']);
		$this->assertSame([['title' => '', 'url' => 'https://example.test', 'snippet' => 'summary', 'source' => 'docs']], $urls);
	}

	public function testCollectUrlsIgnoresEmptyAndInvalidUrls(): void {
		$this->assertSame([], $this->service()->recursiveUrls([['url' => '  '], ['url' => 123], ['name' => 'none']]));
	}

	public function testCollectUrlsIgnoresScalars(): void {
		$this->assertSame([], $this->service()->recursiveUrls('https://example.test'));
	}

	public function testCollectUrlsKeepsOriginalUrlWhitespaceForCaller(): void {
		$urls = $this->service()->recursiveUrls(['url' => ' https://example.test ']);
		$this->assertSame(' https://example.test ', $urls[0]['url']);
	}
}
