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

namespace Base3\MissionBay\Test\Transport;

use Base3\AssistantFoundation\Dto\AiFileResource;
use Base3\MissionBay\Transport\OpenAiCompatibleTransport;
use Base3\MissionBay\Transport\OpenAiTransport;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class OpenAiCompatibleTransportBoundaryTest extends TestCase {

	private function transport(array $options = []): OpenAiCompatibleTransport {
		$transport = new OpenAiCompatibleTransport();
		$transport->setOptions($options);
		return $transport;
	}

	private function invoke(OpenAiCompatibleTransport $transport, string $method, array $arguments): mixed {
		return (new ReflectionMethod(OpenAiCompatibleTransport::class, $method))->invokeArgs($transport, $arguments);
	}

	public function testTransportIdentityIsStable(): void {
		$this->assertSame('openaicompatibletransport', OpenAiCompatibleTransport::getName());
		$this->assertSame('openaitransport', OpenAiTransport::getName());
	}

	public function testOptionsMergeInsteadOfDiscardingExistingConnection(): void {
		$transport = $this->transport(['endpoint' => 'https://example.test/v1', 'apikey' => 'old']);
		$transport->setOptions(['apikey' => 'new']);
		$this->assertSame(['endpoint' => 'https://example.test/v1', 'apikey' => 'new'], $transport->getOptions());
	}

	public function testMissingEndpointCannotMakeRelativeRequest(): void {
		$this->expectException(\RuntimeException::class);
		$this->invoke($this->transport(), 'buildUrl', ['/v1/models']);
	}

	public function testAbsoluteEndpointCanBeUsedWithoutConnectionBase(): void {
		$this->assertSame('https://api.example.test/models', $this->invoke($this->transport(), 'buildUrl', ['https://api.example.test/models']));
	}

	public function testConnectionEndpointPreservesQueryParameters(): void {
		$transport = $this->transport(['endpoint' => 'https://api.example.test/route?api-version=2026']);
		$this->assertSame('https://api.example.test/route?api-version=2026', $this->invoke($transport, 'buildUrl', ['/other']));
	}

	public function testRelativeUrlJoinsAtSingleBoundary(): void {
		$transport = $this->transport(['endpoint' => 'https://api.example.test/v1']);
		$this->assertSame('https://api.example.test/v1/models', $this->invoke($transport, 'buildUrl', ['/v1/models']));
	}

	public function testBearerWithoutSecretIsRejectedBeforeNetwork(): void {
		$this->expectException(\RuntimeException::class);
		$this->invoke($this->transport(), 'buildHeaders', [[]]);
	}

	public function testDefaultBearerAuthenticationIsApplied(): void {
		$this->assertSame(['Content-Type: application/json', 'Authorization: Bearer sample'], $this->invoke($this->transport(['apikey' => 'sample']), 'buildHeaders', [[]]));
	}

	public function testNoAuthModeWorksWithoutApiKey(): void {
		$this->assertSame(['Content-Type: application/json'], $this->invoke($this->transport(['auth_type' => 'none']), 'buildHeaders', [[]]));
	}

	public function testConfiguredApiKeyHeaderDoesNotBecomeBearer(): void {
		$transport = $this->transport(['auth_type' => 'api-key', 'apikey' => 'sample']);
		$this->assertSame(['Content-Type: application/json', 'X-API-Key: sample'], $this->invoke($transport, 'buildHeaders', [[]]));
	}

	public function testCustomApiKeyHeaderNameIsUsed(): void {
		$transport = $this->transport(['auth_type' => 'api-key', 'auth_header_name' => 'Api-Key', 'apikey' => 'sample']);
		$this->assertSame('Api-Key: sample', $this->invoke($transport, 'buildHeaders', [[]])[1]);
	}

	public function testBasicAuthenticationUsesBase64(): void {
		$transport = $this->transport(['auth_type' => 'basic', 'apikey' => 'alice:secret']);
		$this->assertSame('Authorization: Basic ' . base64_encode('alice:secret'), $this->invoke($transport, 'buildHeaders', [[]])[1]);
	}

	public function testRequestOptionsOverrideStoredAuthentication(): void {
		$transport = $this->transport(['auth_type' => 'bearer', 'apikey' => 'stored']);
		$this->assertSame('X-API-Key: override', $this->invoke($transport, 'buildHeaders', [['auth_type' => 'api-key', 'apikey' => 'override']])[1]);
	}

	public function testCustomBearerHeaderNameIsUsed(): void {
		$transport = $this->transport(['apikey' => 'secret', 'auth_header_name' => 'X-Authorization']);
		$this->assertSame('X-Authorization: Bearer secret', $this->invoke($transport, 'buildHeaders', [[]])[1]);
	}

	public function testMultipartHeadersExcludeJsonContentType(): void {
		$this->assertSame(['Authorization: Bearer token'], $this->invoke($this->transport(['apikey' => 'token']), 'buildHeaders', [[], false]));
	}

	public function testCustomHeadersAreFilteredToNonemptyStrings(): void {
		$transport = $this->transport(['auth_type' => 'none']);
		$this->assertSame(['Content-Type: application/json', 'X-Trace: test'], $this->invoke($transport, 'buildHeaders', [['headers' => [0, false, '', '  ', 'X-Trace: test']]]));
	}

	public function testRequestCanDisableConfiguredBearerAuthentication(): void {
		$transport = $this->transport(['auth_type' => 'bearer', 'apikey' => 'token']);
		$this->assertSame(['Content-Type: application/json'], $this->invoke($transport, 'buildHeaders', [['auth_type' => 'none']]));
	}

	public function testTimeoutsUseIndependentDefaults(): void {
		$transport = $this->transport();
		$this->assertSame(60, $this->invoke($transport, 'resolveTimeout', [[]]));
		$this->assertSame(15, $this->invoke($transport, 'resolveConnectTimeout', [[]]));
	}

	public function testRequestTimeoutOverridesStoredValue(): void {
		$transport = $this->transport(['timeout' => 50, 'connect_timeout' => 20]);
		$this->assertSame(3, $this->invoke($transport, 'resolveTimeout', [['timeout' => 3]]));
		$this->assertSame(4, $this->invoke($transport, 'resolveConnectTimeout', [['connect_timeout' => 4]]));
	}

	public function testNonpositiveTimeoutsUseSafeDefaults(): void {
		$transport = $this->transport(['timeout' => -1, 'connect_timeout' => 0]);
		$this->assertSame(60, $this->invoke($transport, 'resolveTimeout', [[]]));
		$this->assertSame(15, $this->invoke($transport, 'resolveConnectTimeout', [[]]));
	}

	public function testRequestRejectsNonJsonSerializablePayloadWithoutNetwork(): void {
		$transport = $this->transport(['endpoint' => 'https://api.example.test', 'auth_type' => 'none']);
		$this->expectException(\RuntimeException::class);
		$transport->request('/v1/chat/completions', ['invalid' => NAN]);
	}

	public function testStreamingRejectsNonJsonSerializablePayloadWithoutNetwork(): void {
		$transport = $this->transport(['endpoint' => 'https://api.example.test', 'auth_type' => 'none']);
		$this->expectException(\RuntimeException::class);
		$transport->stream('/v1/chat/completions', ['invalid' => INF], static function (string $chunk): void {});
	}

	public function testOpenAiUploadRejectsUnstructuredMultipartFieldBeforeNetwork(): void {
		$transport = new OpenAiTransport();
		$transport->setOptions(['endpoint' => 'https://api.example.test', 'auth_type' => 'none']);
		$file = new AiFileResource('/virtual/file.txt', 'file.txt', 'text/plain', 'test', hash('sha256', 'test'));
		$this->expectException(\InvalidArgumentException::class);
		$transport->uploadFile('/v1/files', $file, ['unsafe' => ['nested' => true]]);
	}
}
