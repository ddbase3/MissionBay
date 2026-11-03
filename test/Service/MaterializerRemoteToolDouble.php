<?php declare(strict_types=1);

namespace MissionBay\Test\Service;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentMemory;
use Base3\Event\Api\IEventManager;
use Base3\Logger\Api\ILogger;
use MissionBay\Api\IAgentCapabilitySourceMetadata;
use MissionBay\Api\IAgentComponentPresetRepository;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Api\IAgentContextFactory;
use MissionBay\Api\IAgentResource;
use MissionBay\Api\IAgentResourceFactory;
use MissionBay\Api\IAgentTool;
use MissionBay\Context\AgentContext;
use MissionBay\Resource\AbstractAgentResource;
use MissionBay\Resource\ConfiguredAgentToolResource;
use MissionBay\Service\AgentComponentPresetMaterializer;
use PHPUnit\Framework\TestCase;

final class MaterializerRemoteToolDouble extends AbstractAgentResource implements IAgentTool {

	private string $lastName = '';

	public static function getName(): string {
		return 'materializerremotetooldouble';
	}

	public function getDescription(): string {
		return 'Remote MCP-like tool used to verify configured preset naming.';
	}

	public function getToolDefinitions(): array {
		return [[
			'type' => 'function',
			'readOnlyHint' => true,
			'mutation' => false,
			'requiresApproval' => false,
			'function' => [
				'name' => 'read_wiki_structure',
				'description' => 'Reads a repository documentation structure.',
				'parameters' => [
					'type' => 'object',
					'properties' => [
						'repoName' => ['type' => 'string']
					],
					'required' => ['repoName']
				]
			]
		]];
	}

	public function callTool(string $name, array $arguments, IAgentContext $context): mixed {
		$this->lastName = $name;
		return ['ok' => true];
	}

	public function getLastName(): string {
		return $this->lastName;
	}
}
