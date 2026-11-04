<?php declare(strict_types=1);

namespace MissionBay\Test\Mcp;

use AssistantFoundation\Api\IAgentContext;
use MissionBay\Mcp\McpProfileResourceProvider;
use PHPUnit\Framework\TestCase;

final class McpProfileResourceProviderTest extends TestCase {

	public function testMissingProfileIdExposesNoResource(): void {
		$provider = new McpProfileResourceProvider(['token' => 'secret']);
		$context = $this->createStub(IAgentContext::class);
		$this->assertSame([], $provider->getResourceDefinitions($context));
		$this->assertNull($provider->readResource('missionbay://profile/anything', $context));
	}

	public function testResourceUriIsEncodedAndStrictlyMatched(): void {
		$context = $this->createStub(IAgentContext::class);
		$provider = new McpProfileResourceProvider(['id' => ' with / space ', 'label' => ' Profile ']);
		$definitions = $provider->getResourceDefinitions($context);
		$this->assertCount(1, $definitions);
		$this->assertSame('missionbay://profile/with%20%2F%20space', $definitions[0]['uri']);
		$this->assertSame('application/json', $definitions[0]['mimeType']);
		$this->assertNull($provider->readResource('missionbay://profile/with / space', $context));
		$this->assertSame($definitions[0]['uri'], $provider->readResource($definitions[0]['uri'], $context)['contents'][0]['uri']);
	}

	public function testResourceIncludesOnlySafeMetadataNotBearerToken(): void {
		$provider = new McpProfileResourceProvider([
			'id' => 'main', 'token' => 'private-bearer-secret', 'client_secret' => 'private-client-secret',
			'type' => ' HYBRID ', 'enabled' => 'NO', 'internal_enabled' => '0', 'mcp_enabled' => 'yes',
			'tools' => [8, 'get_time']
		]);
		$context = $this->createStub(IAgentContext::class);
		$json = $provider->readResource('missionbay://profile/main', $context)['contents'][0]['text'];
		$data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
		$this->assertSame('main', $data['id']);
		$this->assertFalse($data['enabled']);
		$this->assertFalse($data['internal_enabled']);
		$this->assertTrue($data['mcp_enabled']);
		$this->assertTrue($data['token_configured']);
		$this->assertSame(['8', 'get_time'], $data['tools']);
		$this->assertSame('MissionBay MCP profile.', $data['description']);
		$this->assertSame('main', $data['label']);
		$this->assertStringNotContainsString('private-bearer-secret', $json);
		$this->assertStringNotContainsString('private-client-secret', $json);
		$this->assertArrayNotHasKey('token', $data);
	}

	public function testProfilesNormalizeDefaultTransportFlagsAndToolPayloads(): void {
		$context = $this->createStub(IAgentContext::class);
		$hybrid = new McpProfileResourceProvider(['id' => 'h', 'type' => 'hybrid']);
		$metadata = json_decode($hybrid->readResource('missionbay://profile/h', $context)['contents'][0]['text'], true);
		$this->assertTrue($metadata['mcp_enabled']);
		$this->assertTrue($metadata['internal_enabled']);
		$this->assertFalse($metadata['token_configured']);
		$this->assertSame([], $metadata['tools']);
		$internal = new McpProfileResourceProvider(['id' => 'i', 'type' => 'internal', 'tools' => 'bad']);
		$metadata = json_decode($internal->readResource('missionbay://profile/i', $context)['contents'][0]['text'], true);
		$this->assertFalse($metadata['mcp_enabled']);
		$this->assertSame([], $metadata['tools']);
	}
}
