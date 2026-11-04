<?php declare(strict_types=1);

namespace MissionBay\Test\Transport;

use MissionBay\Transport\MistralTransport;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class MistralTransportPreparationCoverageTest extends TestCase {

	private function call(MistralTransport $transport, string $method, array $arguments): mixed {
		return (new ReflectionMethod(MistralTransport::class, $method))->invokeArgs($transport, $arguments);
	}

	private function transport(array $options = []): MistralTransport {
		$transport = new MistralTransport();
		$transport->setOptions($options);
		return $transport;
	}

	public function testTechnicalTransportNameIsStable(): void {
		$this->assertSame('mistraltransport', MistralTransport::getName());
	}

	public function testOptionsMergeInsteadOfReplacing(): void {
		$transport = $this->transport(['endpoint' => 'https://api.mistral.ai', 'apikey' => 'a']);
		$transport->setOptions(['apikey' => 'b']);
		$this->assertSame(['endpoint' => 'https://api.mistral.ai', 'apikey' => 'b'], $transport->getOptions());
	}

	public function testMissingEndpointRejectsRelativePath(): void {
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Missing Mistral transport endpoint');
		$this->call($this->transport(), 'buildUrl', ['/v1/files']);
	}

	public function testAbsoluteHttpsUrlDoesNotRequireConfiguredEndpoint(): void {
		$this->assertSame('https://api.example.test/path', $this->call($this->transport(), 'buildUrl', ['https://api.example.test/path']));
	}

	public function testAbsoluteHttpUrlIsAcceptedByExistingTransportContract(): void {
		$this->assertSame('http://localhost:1234/health', $this->call($this->transport(), 'buildUrl', ['http://localhost:1234/health']));
	}

	public function testEmptyPathUsesEndpointExactly(): void {
		$this->assertSame('https://api.example.test/v1', $this->call($this->transport(['endpoint' => ' https://api.example.test/v1 ']), 'buildUrl', ['']));
	}

	public function testRelativePathIsAppendedWithSingleSlash(): void {
		$this->assertSame('https://api.example.test/v1/files', $this->call($this->transport(['endpoint' => 'https://api.example.test/v1/']), 'buildUrl', ['files']));
	}

	public function testLeadingSlashPathIsAppended(): void {
		$this->assertSame('https://api.example.test/v1/files', $this->call($this->transport(['endpoint' => 'https://api.example.test/v1']), 'buildUrl', ['/files']));
	}

	public function testSameFullEndpointPathIsNotDuplicated(): void {
		$this->assertSame('https://api.example.test/v1/files', $this->call($this->transport(['endpoint' => 'https://api.example.test/v1/files']), 'buildUrl', ['/v1/files']));
	}

	public function testRootedPathRemovesRepeatedPrefix(): void {
		$this->assertSame('https://api.example.test/v1/files', $this->call($this->transport(['endpoint' => 'https://api.example.test/v1']), 'buildUrl', ['/v1/files']));
	}

	public function testPathOutsidePrefixIsAppendedNormally(): void {
		$this->assertSame('https://api.example.test/v1/models', $this->call($this->transport(['endpoint' => 'https://api.example.test/v1']), 'buildUrl', ['/models']));
	}

	public function testMissingApiKeyRejectsHeaders(): void {
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Missing API key');
		$this->call($this->transport(), 'buildHeaders', [[]]);
	}

	public function testDefaultJsonHeadersIncludeBearerAuth(): void {
		$this->assertSame(['Content-Type: application/json', 'Authorization: Bearer test-key'], $this->call($this->transport(['apikey' => 'test-key']), 'buildHeaders', [[]]));
	}

	public function testMultipartHeadersOmitJsonContentType(): void {
		$this->assertSame(['Authorization: Bearer test-key'], $this->call($this->transport(['apikey' => 'test-key']), 'buildHeaders', [[], false]));
	}

	public function testRequestApiKeyOverridesConfiguredApiKey(): void {
		$this->assertSame(['Content-Type: application/json', 'Authorization: Bearer override'], $this->call($this->transport(['apikey' => 'base']), 'buildHeaders', [['apikey' => ' override ']]));
	}

	public function testAdditionalNonEmptyHeadersAreKept(): void {
		$this->assertSame(['Content-Type: application/json', 'Authorization: Bearer key', 'X-Request-ID: test'], $this->call($this->transport(['apikey' => 'key']), 'buildHeaders', [['headers' => [' ', 4, 'X-Request-ID: test']]]));
	}

	public function testDefaultTimeoutsAreBoundedAndIndependent(): void {
		$transport = $this->transport();
		$this->assertSame(60, $this->call($transport, 'resolveTimeout', [[]]));
		$this->assertSame(15, $this->call($transport, 'resolveConnectTimeout', [[]]));
	}

	public function testConfiguredTimeoutOverridesDefault(): void {
		$this->assertSame(24, $this->call($this->transport(['timeout' => 24]), 'resolveTimeout', [[]]));
	}

	public function testConfiguredConnectTimeoutOverridesDefault(): void {
		$this->assertSame(7, $this->call($this->transport(['connect_timeout' => 7]), 'resolveConnectTimeout', [[]]));
	}

	public function testRequestTimeoutTakesPrecedence(): void {
		$this->assertSame(3, $this->call($this->transport(['timeout' => 24]), 'resolveTimeout', [['timeout' => 3]]));
	}

	public function testRequestConnectTimeoutTakesPrecedence(): void {
		$this->assertSame(3, $this->call($this->transport(['connect_timeout' => 7]), 'resolveConnectTimeout', [['connect_timeout' => 3]]));
	}

	public function testNonpositiveTimeoutFallsBackToSixtySeconds(): void {
		$this->assertSame(60, $this->call($this->transport(['timeout' => 0]), 'resolveTimeout', [[]]));
		$this->assertSame(60, $this->call($this->transport(), 'resolveTimeout', [['timeout' => -10]]));
	}

	public function testNonpositiveConnectTimeoutFallsBackToFifteenSeconds(): void {
		$this->assertSame(15, $this->call($this->transport(['connect_timeout' => -1]), 'resolveConnectTimeout', [[]]));
		$this->assertSame(15, $this->call($this->transport(), 'resolveConnectTimeout', [['connect_timeout' => 0]]));
	}
}
