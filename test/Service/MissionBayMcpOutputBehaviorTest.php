<?php declare(strict_types=1);

namespace MissionBay\Test\Service;

use Base3\Api\IClassMap;
use Base3\Api\IRequest;
use Base3\Logger\Api\ILogger;
use Base3\Token\Api\IToken;
use MissionBay\Api\IMcpAgent;
use MissionBay\Service\MissionBayMcp;
use PHPUnit\Framework\TestCase;

final class MissionBayMcpOutputBehaviorTest extends TestCase {

	public function testJsonOnlyEndpointRejectsOtherFormatsBeforeTouchingServices(): void {
		$map = $this->createMock(IClassMap::class);
		$map->expects($this->never())->method('getInstancesByInterface');
		$endpoint = $this->endpoint($map, $this->createStub(IRequest::class));
		$this->assertSame('This endpoint only supports JSON output.', $endpoint->getOutput('html'));
	}

	public function testReadOnlyDiscoveryExposesOpenApiWithoutAgents(): void {
		$map = $this->createMock(IClassMap::class);
		$map->expects($this->once())->method('getInstancesByInterface')->with(IMcpAgent::class)->willReturn([]);
		$request = $this->createStub(IRequest::class);
		$request->method('getContext')->willReturn(IRequest::CONTEXT_WEB_GET);
		$definition = json_decode($this->endpoint($map, $request)->getOutput(), true);
		$this->assertSame('3.1.0', $definition['openapi']);
		$this->assertSame([], $definition['paths']);
		$this->assertArrayHasKey('x-debug-id', $definition);
	}

	public function testReadOnlyDiscoveryBuildsRequiredPropertiesFromAgentSpec(): void {
		$agent = $this->createStub(IMcpAgent::class);
		$agent->method('getFunctionName')->willReturn('lookup');
		$agent->method('getDescription')->willReturn('Lookup items');
		$agent->method('getInputSpec')->willReturn([
			'query' => ['type' => 'string', 'required' => true, 'description' => 'Search text'],
			'limit' => ['type' => 'integer', 'required' => false]
		]);
		$agent->method('getOutputSpec')->willReturn(['count' => ['type' => 'integer']]);
		$map = $this->createStub(IClassMap::class);
		$map->method('getInstancesByInterface')->willReturn([$agent]);
		$request = $this->createStub(IRequest::class);
		$request->method('getContext')->willReturn(IRequest::CONTEXT_WEB_GET);
		$definition = json_decode($this->endpoint($map, $request)->getOutput(), true);
		$post = $definition['paths']['/functions/lookup']['post'];
		$this->assertSame(['query'], $post['requestBody']['content']['application/json']['schema']['required']);
		$this->assertSame('integer', $post['requestBody']['content']['application/json']['schema']['properties']['limit']['type']);
		$this->assertSame('Lookup items', $post['summary']);
		$this->assertSame('integer', $post['responses']['200']['content']['application/json']['schema']['properties']['count']['type']);
	}

	public function testEndpointIdentifiesItselfWithoutAccessingSecrets(): void {
		$endpoint = $this->endpoint($this->createStub(IClassMap::class), $this->createStub(IRequest::class));
		$this->assertSame('missionbaymcp', MissionBayMcp::getName());
		$this->assertStringContainsString('OpenAPI 3.1', $endpoint->getHelp());
	}

	private function endpoint(IClassMap $classMap, IRequest $request): MissionBayMcp {
		return new MissionBayMcp($classMap, $request, $this->createStub(IToken::class), $this->createStub(ILogger::class));
	}
}
