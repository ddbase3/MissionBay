<?php declare(strict_types=1);

namespace MissionBay\Test\Resource\Mcp;

use AssistantFoundation\Api\IAgentContext;
use Base3\Logger\Api\ILogger;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Api\IMcpClient;
use MissionBay\Api\IMcpClientFactory;
use MissionBay\Context\AgentContext;
use MissionBay\Dto\Mcp\McpClientConfig;
use MissionBay\Mcp\Client\McpRemoteToolDefinitionMapper;
use MissionBay\Mcp\Client\McpRemoteToolResultMapper;
use MissionBay\Resource\Mcp\McpClientAgentResource;
use PHPUnit\Framework\TestCase;

final class McpClientAgentResourceTest extends TestCase {

	public function testSchemaDoesNotDefineAnotherToolNamespace(): void {
		$resource = new McpClientAgentResource(
			new McpAgentResourceClientFactoryDouble(new McpAgentResourceClientDouble()),
			new McpAgentResourceIdentityResolver(),
			new McpRemoteToolDefinitionMapper(),
			new McpRemoteToolResultMapper(),
			new McpAgentResourceNullLogger()
		);

		$properties = $resource->getSchema()['properties'] ?? [];

		$this->assertArrayNotHasKey('namespace', $properties);
		$this->assertArrayNotHasKey('mutation_policy', $properties);
	}

	public function testClassMapInstanceWithoutPresetConfigRemainsDormant(): void {
		$resource = new McpClientAgentResource(
			new McpAgentResourceClientFactoryDouble(new McpAgentResourceClientDouble()),
			new McpAgentResourceIdentityResolver(),
			new McpRemoteToolDefinitionMapper(),
			new McpRemoteToolResultMapper(),
			new McpAgentResourceNullLogger()
		);
		$context = new AgentContext();

		$this->assertSame([], $resource->getToolDefinitions());
		$this->assertSame([], $resource->getResourceDefinitions($context));
		$this->assertSame([], $resource->getPromptDefinitions($context));
		$this->assertSame([], iterator_to_array((function() use ($resource, $context): iterable {
			yield from $resource->contribute($context);
		})()));
	}

	public function testRemoteAnnotationsUseExistingMissionBayApprovalContract(): void {
		$client = new McpAgentResourceClientDouble();
		$resource = $this->createResource($client, []);

		$definitions = $resource->getToolDefinitions();
		$this->assertSame(['read_file', 'write_file'], array_column(array_column($definitions, 'function'), 'name'));
		$this->assertFalse($definitions[0]['mutation']);
		$this->assertTrue($definitions[1]['mutation']);
		$this->assertTrue($definitions[1]['requiresApproval']);
		$this->assertFalse($definitions[1]['commitGuardRequired']);

		$context = new AgentContext();
		$this->assertSame(null, $resource->getConfirmationRequest('read_file', [], $context));
		$confirmation = $resource->getConfirmationRequest(
			'write_file',
			['path' => 'README.md', 'content' => 'updated'],
			$context
		);
		$this->assertSame('Confirm remote MCP action', $confirmation['title']);
		$this->assertSame('high', $confirmation['risk']);
		$this->assertSame('write_file', $confirmation['summary']['Remote name']);
		$this->assertSame([
			'readOnlyHint' => false,
			'destructiveHint' => true,
			'idempotentHint' => false,
			'openWorldHint' => false
		], $confirmation['summary']['MCP safety hints']);
		$this->assertSame(['path' => 'README.md', 'content' => 'updated'], $confirmation['summary']['Arguments']);

		$result = $resource->callTool('write_file', ['path' => 'README.md'], $context);
		$this->assertSame([
			'remote_tool' => 'write_file',
			'arguments' => ['path' => 'README.md']
		], $result);
		$this->assertSame('write_file', $client->getLastToolName());

		$schemas = $resource->getOutputSchemas();
		$this->assertSame('object', $schemas['write_file']['type']);
	}

	public function testUnannotatedRemoteToolRemainsAvailableAndRequiresApproval(): void {
		$client = new McpAgentResourceClientDouble([[
			'name' => 'read_wiki_structure',
			'description' => 'Get a list of documentation topics for a GitHub repository.',
			'inputSchema' => [
				'type' => 'object',
				'properties' => [
					'repoName' => ['type' => 'string']
				],
				'required' => ['repoName']
			]
		]]);
		$resource = $this->createResource($client, []);
		$definitions = $resource->getToolDefinitions();

		$this->assertCount(1, $definitions);
		$this->assertSame('read_wiki_structure', $definitions[0]['function']['name']);
		$this->assertTrue($definitions[0]['mutation']);
		$this->assertTrue($definitions[0]['requiresApproval']);
		$confirmation = $resource->getConfirmationRequest(
			'read_wiki_structure',
			['repoName' => 'modelcontextprotocol/servers'],
			new AgentContext()
		);
		$this->assertNotNull($confirmation);
		$this->assertSame('high', $confirmation['risk']);
		$this->assertSame([
			'readOnlyHint',
			'destructiveHint',
			'idempotentHint',
			'openWorldHint'
		], $confirmation['summary']['Missing MCP safety hints']);
	}

	public function testResourcesPromptsAndConfiguredContextAreDelegated(): void {
		$client = new McpAgentResourceClientDouble();
		$resource = $this->createResource($client, [
			'include_server_instructions' => true,
			'context_resources' => ['repo://readme'],
			'context_prompts' => [[
				'name' => 'review',
				'arguments' => ['target' => 'pull-request']
			]]
		]);
		$context = new AgentContext();

		$resources = $resource->getResourceDefinitions($context);
		$this->assertSame('repo://readme', $resources[0]['uri']);
		$this->assertSame('repo://file/{path}', $resources[1]['uriTemplate']);
		$this->assertSame('binary-data', $resource->readResource('repo://file/image.png', $context)['contents'][0]['blob']);

		$prompts = $resource->getPromptDefinitions($context);
		$this->assertSame('review', $prompts[0]['name']);
		$prompt = $resource->getPrompt('review', ['target' => 'issue'], $context);
		$this->assertSame('Review issue', $prompt['messages'][0]['content']['text']);

		$blocks = iterator_to_array((function() use ($resource, $context): iterable {
			yield from $resource->contribute($context);
		})());
		$this->assertCount(3, $blocks);
		$this->assertSame('Use this test MCP server carefully.', $blocks[0]->getContent());
		$this->assertStringContainsString('Repository readme', $blocks[1]->getContent());
		$this->assertStringContainsString('Review pull-request', $blocks[2]->getContent());
	}

	/** @param array<string,mixed> $config */
	private function createResource(IMcpClient $client, array $config): McpClientAgentResource {
		$factory = new McpAgentResourceClientFactoryDouble($client);
		$resource = new McpClientAgentResource(
			$factory,
			new McpAgentResourceIdentityResolver(),
			new McpRemoteToolDefinitionMapper(),
			new McpRemoteToolResultMapper(),
			new McpAgentResourceNullLogger(),
			'remote-server'
		);
		$resource->setConfig(array_replace([
			'endpoint' => 'https://mcp.example.test/mcp',
			'auth_type' => 'none'
		], $config));

		return $resource;
	}
}

require_once __DIR__ . '/McpAgentResourceClientFactoryDouble.php';
require_once __DIR__ . '/McpAgentResourceClientDouble.php';
require_once __DIR__ . '/McpAgentResourceIdentityResolver.php';
require_once __DIR__ . '/McpAgentResourceNullLogger.php';
