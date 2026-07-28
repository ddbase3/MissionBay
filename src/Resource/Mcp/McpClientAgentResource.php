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

namespace MissionBay\Resource\Mcp;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentContextContributor;
use AssistantFoundation\Dto\AgentInstructionBlock;
use Base3\Api\IOutputSchemaProvider;
use Base3\Api\ISchemaProvider;
use Base3\Logger\Api\ILogger;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Api\IAgentPromptProvider;
use MissionBay\Api\IAgentResourceProvider;
use MissionBay\Api\IAgentTool;
use MissionBay\Api\IConfirmableAgentTool;
use MissionBay\Api\IMcpClient;
use MissionBay\Api\IMcpClientFactory;
use MissionBay\Dto\Mcp\McpClientConfig;
use MissionBay\Mcp\Client\McpRemoteToolDefinitionMapper;
use MissionBay\Mcp\Client\McpRemoteToolResultMapper;
use MissionBay\Resource\AbstractAgentResource;

/**
 * Exposes a remote Streamable HTTP MCP server as one configured MissionBay
 * agent resource with tools, resources, prompts, and optional context blocks.
 */
final class McpClientAgentResource extends AbstractAgentResource implements
	IAgentTool,
	IConfirmableAgentTool,
	IAgentResourceProvider,
	IAgentPromptProvider,
	IAgentContextContributor,
	ISchemaProvider,
	IOutputSchemaProvider {

	private const LOG_SCOPE = 'missionbay_mcp_client';

	private bool $configApplied = false;

	/** @var array<string,mixed> */
	private array $resolvedClientConfig = [];
	private string $label = '';
	private bool $includeServerInstructions = true;
	private int $contextPriority = 35;

	/** @var array<int,string> */
	private array $toolAllowlist = [];

	/** @var array<int,string> */
	private array $toolDenylist = [];

	/** @var array<int,string> */
	private array $contextResources = [];

	/** @var array<int,mixed> */
	private array $contextPrompts = [];

	private ?IMcpClient $client = null;
	private ?McpClientConfig $clientConfig = null;

	/** @var array<int,array<string,mixed>>|null */
	private ?array $toolDefinitions = null;

	/** @var array<string,bool> */
	private array $toolNames = [];

	/** @var array<string,array<string,mixed>> */
	private array $outputSchemas = [];

	/** @var array<string,array{remote_name:string,label:string,risk:string,hints:array<string,bool>,missing:array<int,string>,contradictory:bool}> */
	private array $confirmationDefinitions = [];

	/** @var array<int,array<string,mixed>>|null */
	private ?array $resourceDefinitions = null;

	/** @var array<string,bool> */
	private array $resourceUris = [];

	/** @var array<int,string> */
	private array $resourceTemplates = [];

	/** @var array<int,array<string,mixed>>|null */
	private ?array $promptDefinitions = null;

	/** @var array<string,bool> */
	private array $promptNames = [];

	public function __construct(
		private readonly IMcpClientFactory $clientFactory,
		private readonly IAgentConfigValueResolver $resolver,
		private readonly McpRemoteToolDefinitionMapper $toolDefinitionMapper,
		private readonly McpRemoteToolResultMapper $toolResultMapper,
		private readonly ILogger $logger,
		?string $id = null
	) {
		parent::__construct($id);
	}

	public static function getName(): string {
		return 'mcpclientagentresource';
	}

	public function getDescription(): string {
		return 'Connects one remote Streamable HTTP MCP server and exposes its tools, resources, prompts, and selected context.';
	}

	/** @return array<string,mixed> */
	public function getSchema(): array {
		return [
			'$schema' => 'https://json-schema.org/draft-2020-12/schema',
			'type' => 'object',
			'properties' => [
				'endpoint' => [
					'type' => 'string',
					'description' => 'Absolute Streamable HTTP MCP endpoint.',
					'format' => 'uri'
				],
				'auth_type' => [
					'type' => 'string',
					'description' => 'Authentication header strategy.',
					'enum' => ['none', 'bearer', 'api_key', 'basic'],
					'default' => 'bearer'
				],
				'token' => [
					'type' => 'string',
					'description' => 'Bearer token, API key, or basic-auth password. ConfigValue definitions are supported.',
					'x-ui' => [
						'control' => 'password',
						'sensitive' => true
					]
				],
				'username' => [
					'type' => 'string',
					'description' => 'Username used only with basic authentication.'
				],
				'auth_header_name' => [
					'type' => 'string',
					'description' => 'Header name used with api_key authentication.',
					'default' => 'X-API-Key'
				],
				'headers' => [
					'type' => 'object',
					'description' => 'Additional non-reserved HTTP headers. Each value may be a ConfigValue definition.',
					'additionalProperties' => true,
					'default' => new \stdClass()
				],
				'label' => [
					'type' => 'string',
					'description' => 'Optional display label for the remote capability provider.'
				],
				'protocol_version' => [
					'type' => 'string',
					'description' => 'MCP protocol version or auto negotiation.',
					'enum' => ['auto', '2025-11-25', '2025-06-18', '2025-03-26'],
					'default' => 'auto'
				],
				'connect_timeout' => [
					'type' => 'integer',
					'description' => 'Connection timeout in seconds.',
					'minimum' => 1,
					'maximum' => 120,
					'default' => 10
				],
				'request_timeout' => [
					'type' => 'integer',
					'description' => 'Maximum duration of one MCP request in seconds.',
					'minimum' => 1,
					'maximum' => 600,
					'default' => 60
				],
				'max_response_bytes' => [
					'type' => 'integer',
					'description' => 'Maximum response body size.',
					'minimum' => 1024,
					'default' => 2097152
				],
				'max_pages' => [
					'type' => 'integer',
					'description' => 'Maximum number of list pages read from one MCP primitive.',
					'minimum' => 1,
					'default' => 20
				],
				'max_items' => [
					'type' => 'integer',
					'description' => 'Maximum total list items read from one MCP primitive.',
					'minimum' => 1,
					'default' => 500
				],
				'verify_tls' => [
					'type' => 'boolean',
					'description' => 'Verify the remote TLS certificate and host name.',
					'default' => true
				],
				'tool_allowlist' => [
					'type' => 'array',
					'description' => 'Optional remote tool names or shell-style patterns that may be exposed.',
					'items' => ['type' => 'string'],
					'default' => []
				],
				'tool_denylist' => [
					'type' => 'array',
					'description' => 'Remote tool names or shell-style patterns that must not be exposed.',
					'items' => ['type' => 'string'],
					'default' => []
				],
				'include_server_instructions' => [
					'type' => 'boolean',
					'description' => 'Add instructions returned by initialize to the agent context.',
					'default' => true
				],
				'context_resources' => [
					'type' => 'array',
					'description' => 'Remote resource URIs read and added to the agent context.',
					'items' => ['type' => 'string'],
					'default' => []
				],
				'context_prompts' => [
					'type' => 'array',
					'description' => 'Remote prompts added to context. Entries may be names or objects with name and arguments.',
					'items' => [
						'oneOf' => [
							['type' => 'string'],
							[
								'type' => 'object',
								'properties' => [
									'name' => ['type' => 'string'],
									'arguments' => ['type' => 'object']
								],
								'required' => ['name']
							]
						]
					],
					'default' => []
				],
				'context_priority' => [
					'type' => 'integer',
					'description' => 'Context contribution priority. Lower values are loaded first.',
					'minimum' => 0,
					'maximum' => 1000,
					'default' => 35
				]
			],
			'required' => ['endpoint', 'auth_type']
		];
	}

	public function setConfig(array $config): void {
		parent::setConfig($config);
		$this->configApplied = true;
		$this->resetRuntimeState();

		$headers = $this->resolveHeaderMap($config['headers'] ?? []);
		$this->resolvedClientConfig = [
			'endpoint' => $this->resolveString($config['endpoint'] ?? null),
			'auth_type' => strtolower($this->resolveString($config['auth_type'] ?? 'bearer')),
			'token' => $this->resolveSecret($config['token'] ?? null),
			'username' => $this->resolveString($config['username'] ?? null),
			'auth_header_name' => $this->resolveString($config['auth_header_name'] ?? 'X-API-Key'),
			'headers' => $headers,
			'protocol_version' => $this->resolveString($config['protocol_version'] ?? 'auto'),
			'connect_timeout' => $this->resolveInt($config['connect_timeout'] ?? 10, 10),
			'request_timeout' => $this->resolveInt($config['request_timeout'] ?? 60, 60),
			'max_response_bytes' => $this->resolveInt($config['max_response_bytes'] ?? 2097152, 2097152),
			'max_pages' => $this->resolveInt($config['max_pages'] ?? 20, 20),
			'max_items' => $this->resolveInt($config['max_items'] ?? 500, 500),
			'verify_tls' => $this->resolveBool($config['verify_tls'] ?? true, true)
		];

		$this->label = $this->resolveString($config['label'] ?? null);

		$this->toolAllowlist = $this->resolveStringList($config['tool_allowlist'] ?? []);
		$this->toolDenylist = $this->resolveStringList($config['tool_denylist'] ?? []);
		$this->includeServerInstructions = $this->resolveBool($config['include_server_instructions'] ?? true, true);
		$this->contextResources = $this->resolveStringList($config['context_resources'] ?? []);
		$this->contextPrompts = $this->resolveList($config['context_prompts'] ?? []);
		$this->contextPriority = max(0, min(1000, $this->resolveInt($config['context_priority'] ?? 35, 35)));
	}

	/** @return array<int,array<string,mixed>> */
	public function getToolDefinitions(): array {
		if(!$this->configApplied) {
			return [];
		}

		if($this->toolDefinitions !== null) {
			return $this->toolDefinitions;
		}

		$this->toolNames = [];
		$this->outputSchemas = [];
		$this->confirmationDefinitions = [];
		$definitions = [];

		foreach($this->getClient()->listTools() as $remoteTool) {
			$remoteName = trim((string)($remoteTool['name'] ?? ''));
			if($remoteName === '' || !$this->isToolAllowed($remoteName)) {
				continue;
			}

			$name = $remoteName;
			if(isset($this->toolNames[$name])) {
				throw new \RuntimeException('Duplicate remote MCP tool name: ' . $remoteName);
			}

			$definition = $this->toolDefinitionMapper->toAgentTool($remoteTool);

			$this->toolNames[$name] = true;
			if(($definition['mutation'] ?? false) === true) {
				$annotations = is_array($definition['annotations'] ?? null) ? $definition['annotations'] : [];
				$this->confirmationDefinitions[$name] = [
					'remote_name' => $remoteName,
					'label' => trim((string)($definition['label'] ?? $remoteName)),
					'risk' => trim((string)($annotations['riskHint'] ?? 'high')) ?: 'high',
					'hints' => [
						'readOnlyHint' => ($annotations['readOnlyHint'] ?? false) === true,
						'destructiveHint' => ($annotations['destructiveHint'] ?? true) === true,
						'idempotentHint' => ($annotations['idempotentHint'] ?? false) === true,
						'openWorldHint' => ($annotations['openWorldHint'] ?? true) === true
					],
					'missing' => is_array($annotations['mcpMissingHints'] ?? null)
						? array_values(array_filter($annotations['mcpMissingHints'], 'is_string'))
						: [],
					'contradictory' => ($annotations['mcpHintsContradictory'] ?? false) === true
				];
			}
			if(is_array($remoteTool['outputSchema'] ?? null)) {
				$this->outputSchemas[$name] = $remoteTool['outputSchema'];
			}
			$definitions[] = $definition;
		}

		$this->toolDefinitions = $definitions;
		$this->logger->logLevel(ILogger::INFO, 'Remote MCP tools loaded.', [
			'scope' => self::LOG_SCOPE,
			'resource' => $this->id(),
			'count' => count($definitions)
		]);

		return $this->toolDefinitions;
	}

	public function callTool(string $name, array $arguments, IAgentContext $context): mixed {
		if($this->toolNames === []) {
			$this->getToolDefinitions();
		}

		$remoteName = isset($this->toolNames[$name]) ? $name : null;
		if($remoteName === null) {
			throw new \InvalidArgumentException('Unsupported remote MCP tool: ' . $name);
		}

		try {
			$result = $this->getClient()->callTool($remoteName, $arguments);
			return $this->toolResultMapper->toAgentResult($result);
		}
		catch(\Throwable $e) {
			$message = $this->redact($e->getMessage());
			$this->logger->logLevel(ILogger::ERROR, 'Remote MCP tool call failed.', [
				'scope' => self::LOG_SCOPE,
				'resource' => $this->id(),
				'tool' => $name,
				'remote_tool' => $remoteName,
				'error' => $message
			]);
			throw new \RuntimeException('Remote MCP tool call failed: ' . $message);
		}
	}

	public function getConfirmationRequest(string $name, array $arguments, IAgentContext $context): ?array {
		if($this->toolDefinitions === null) {
			$this->getToolDefinitions();
		}

		$confirmation = $this->confirmationDefinitions[$name] ?? null;

		if(!is_array($confirmation)) {
			return null;
		}

		$summary = [
			'Provider' => $this->getProviderLabel(),
			'Tool' => $confirmation['label'],
			'Remote name' => $confirmation['remote_name'],
			'MCP safety hints' => $confirmation['hints'],
			'Arguments' => $arguments
		];

		if($confirmation['missing'] !== []) {
			$summary['Missing MCP safety hints'] = $confirmation['missing'];
		}
		if($confirmation['contradictory']) {
			$summary['MCP safety warning'] = 'The remote safety hints are contradictory.';
		}

		return [
			'title' => 'Confirm remote MCP action',
			'message' => 'Execute the remote MCP tool ' . $confirmation['remote_name'] . '? The remote safety hints do not establish a low-risk read-only operation.',
			'summary' => $summary,
			'risk' => $confirmation['risk'] === 'medium' ? 'medium' : 'high'
		];
	}

	/** @return array<string,array<string,mixed>> */
	public function getOutputSchemas(): array {
		if($this->toolDefinitions === null) {
			$this->getToolDefinitions();
		}

		return $this->outputSchemas;
	}

	/** @return array<int,array<string,mixed>> */
	public function getResourceDefinitions(IAgentContext $context): array {
		if(!$this->configApplied) {
			return [];
		}

		if($this->resourceDefinitions !== null) {
			return $this->resourceDefinitions;
		}

		$this->resourceUris = [];
		$this->resourceTemplates = [];
		$definitions = [];

		foreach($this->getClient()->listResources() as $resource) {
			$uri = trim((string)($resource['uri'] ?? ''));
			if($uri === '') {
				continue;
			}
			if(isset($this->resourceUris[$uri])) {
				throw new \RuntimeException('Remote MCP server returned a duplicate resource URI: ' . $uri);
			}
			$this->resourceUris[$uri] = true;
			$definitions[] = $resource;
		}

		foreach($this->getClient()->listResourceTemplates() as $template) {
			$uriTemplate = trim((string)($template['uriTemplate'] ?? ''));
			if($uriTemplate === '') {
				continue;
			}
			if(in_array($uriTemplate, $this->resourceTemplates, true)) {
				throw new \RuntimeException('Remote MCP server returned a duplicate resource template: ' . $uriTemplate);
			}
			$this->resourceTemplates[] = $uriTemplate;
			$definitions[] = $template;
		}

		$this->resourceDefinitions = $definitions;
		return $this->resourceDefinitions;
	}

	public function readResource(string $uri, IAgentContext $context): ?array {
		$uri = trim($uri);
		if($uri === '') {
			return null;
		}

		if($this->resourceDefinitions === null) {
			$this->getResourceDefinitions($context);
		}

		if(!isset($this->resourceUris[$uri]) && !$this->matchesResourceTemplate($uri)) {
			return null;
		}

		return $this->getClient()->readResource($uri);
	}

	/** @return array<int,array<string,mixed>> */
	public function getPromptDefinitions(IAgentContext $context): array {
		if(!$this->configApplied) {
			return [];
		}

		if($this->promptDefinitions !== null) {
			return $this->promptDefinitions;
		}

		$this->promptNames = [];
		$definitions = [];

		foreach($this->getClient()->listPrompts() as $prompt) {
			$remoteName = trim((string)($prompt['name'] ?? ''));
			if($remoteName === '') {
				continue;
			}

			$name = $remoteName;
			if(isset($this->promptNames[$name])) {
				throw new \RuntimeException('Duplicate remote MCP prompt name: ' . $remoteName);
			}

			$prompt['name'] = $name;
			$this->promptNames[$name] = true;
			$definitions[] = $prompt;
		}

		$this->promptDefinitions = $definitions;
		return $this->promptDefinitions;
	}

	public function getPrompt(string $name, array $arguments, IAgentContext $context): ?array {
		if($this->promptNames === []) {
			$this->getPromptDefinitions($context);
		}

		$remoteName = isset($this->promptNames[$name]) ? $name : null;
		if($remoteName === null) {
			return null;
		}

		return $this->getClient()->getPrompt($remoteName, $arguments);
	}

	public function contribute(IAgentContext $context): iterable {
		if(!$this->configApplied) {
			return [];
		}

		$blocks = [];
		$priority = $this->getPriority();

		if($this->includeServerInstructions) {
			$initialize = $this->getClient()->getInitializeResult();
			$instructions = trim((string)($initialize['instructions'] ?? ''));
			if($instructions !== '') {
				$blocks[] = new AgentInstructionBlock(
					id: 'mcp-instructions:' . $this->id(),
					content: $instructions,
					priority: $priority,
					source: $this->id(),
					metadata: [
						'implementation' => self::getName(),
						'kind' => 'server-instructions',
					]
				);
			}
		}

		foreach($this->contextResources as $uri) {
			$result = $this->getClient()->readResource($uri);
			$content = $this->resourceResultToText($result, $uri);
			if($content === '') {
				continue;
			}
			$blocks[] = new AgentInstructionBlock(
				id: 'mcp-resource:' . $this->id() . ':' . sha1($uri),
				content: $content,
				priority: $priority,
				source: $this->id(),
				metadata: [
					'implementation' => self::getName(),
					'kind' => 'resource',
					'uri' => $uri
				]
			);
		}

		foreach($this->normalizeContextPromptEntries($context) as $entry) {
			$result = $this->getClient()->getPrompt($entry['name'], $entry['arguments']);
			$content = $this->promptResultToText($result, $entry['name']);
			if($content === '') {
				continue;
			}
			$blocks[] = new AgentInstructionBlock(
				id: 'mcp-prompt:' . $this->id() . ':' . sha1($entry['name'] . '|' . json_encode($entry['arguments'])),
				content: $content,
				priority: $priority,
				source: $this->id(),
				metadata: [
					'implementation' => self::getName(),
					'kind' => 'prompt',
					'name' => $entry['name']
				]
			);
		}

		return $blocks;
	}

	public function getPriority(): int {
		return $this->contextPriority;
	}

	private function getProviderLabel(): string {
		return $this->label !== '' ? $this->label : $this->id();
	}

	private function getClient(): IMcpClient {
		if(!$this->configApplied) {
			throw new \RuntimeException('Remote MCP client resource has not been configured.');
		}

		if($this->client instanceof IMcpClient) {
			return $this->client;
		}

		try {
			$this->clientConfig = McpClientConfig::fromArray($this->resolvedClientConfig);
			$this->client = $this->clientFactory->create($this->clientConfig);
			return $this->client;
		}
		catch(\Throwable $e) {
			throw new \RuntimeException('Unable to configure remote MCP client: ' . $this->redact($e->getMessage()));
		}
	}

	private function resetRuntimeState(): void {
		$this->client = null;
		$this->clientConfig = null;
		$this->toolDefinitions = null;
		$this->toolNames = [];
		$this->outputSchemas = [];
		$this->confirmationDefinitions = [];
		$this->resourceDefinitions = null;
		$this->resourceUris = [];
		$this->resourceTemplates = [];
		$this->promptDefinitions = null;
		$this->promptNames = [];
	}

	private function resolveString(mixed $value): string {
		if(!is_array($value) || array_key_exists('mode', $value)) {
			$value = $this->resolver->resolveValue($value);
		}

		return is_scalar($value) || $value === null ? trim((string)$value) : '';
	}

	private function resolveSecret(mixed $value): string {
		if(!is_array($value) || array_key_exists('mode', $value)) {
			$value = $this->resolver->resolveValue($value);
		}

		return is_scalar($value) || $value === null ? (string)$value : '';
	}

	private function resolveInt(mixed $value, int $default): int {
		if(!is_array($value) || array_key_exists('mode', $value)) {
			$value = $this->resolver->resolveValue($value);
		}

		return is_numeric($value) ? (int)$value : $default;
	}

	private function resolveBool(mixed $value, bool $default): bool {
		if(!is_array($value) || array_key_exists('mode', $value)) {
			$value = $this->resolver->resolveValue($value);
		}

		if(is_bool($value)) {
			return $value;
		}
		if(is_int($value)) {
			return $value !== 0;
		}
		if(!is_scalar($value) || trim((string)$value) === '') {
			return $default;
		}

		$value = strtolower(trim((string)$value));
		if(in_array($value, ['1', 'true', 'yes', 'on'], true)) {
			return true;
		}
		if(in_array($value, ['0', 'false', 'no', 'off'], true)) {
			return false;
		}

		return $default;
	}

	/** @return array<int,mixed> */
	private function resolveList(mixed $value): array {
		if(!is_array($value) || array_key_exists('mode', $value)) {
			$value = $this->resolver->resolveValue($value);
		}

		if(is_string($value)) {
			$value = explode(',', $value);
		}

		return is_array($value) ? array_values($value) : [];
	}

	/** @return array<int,string> */
	private function resolveStringList(mixed $value): array {
		$result = [];
		foreach($this->resolveList($value) as $item) {
			if(!is_scalar($item)) {
				continue;
			}
			$item = trim((string)$item);
			if($item !== '') {
				$result[$item] = true;
			}
		}

		return array_keys($result);
	}

	/** @return array<string,string> */
	private function resolveHeaderMap(mixed $value): array {
		if(!is_array($value) || array_key_exists('mode', $value)) {
			$value = $this->resolver->resolveValue($value);
		}

		if(!is_array($value)) {
			return [];
		}

		$headers = [];
		foreach($value as $name => $headerValue) {
			$name = trim((string)$name);
			if($name === '') {
				continue;
			}
			if(is_array($headerValue) && array_key_exists('mode', $headerValue)) {
				$headerValue = $this->resolver->resolveValue($headerValue);
			}
			if(!is_scalar($headerValue) && $headerValue !== null) {
				throw new \InvalidArgumentException('MCP header value must resolve to a scalar: ' . $name);
			}
			$headers[$name] = (string)$headerValue;
		}

		return $headers;
	}

	private function isToolAllowed(string $remoteName): bool {
		if($this->matchesAny($remoteName, $this->toolDenylist)) {
			return false;
		}

		return $this->toolAllowlist === [] || $this->matchesAny($remoteName, $this->toolAllowlist);
	}

	/** @param array<int,string> $patterns */
	private function matchesAny(string $value, array $patterns): bool {
		foreach($patterns as $pattern) {
			if(function_exists('fnmatch') && fnmatch($pattern, $value, FNM_NOESCAPE)) {
				return true;
			}
			if($pattern === $value) {
				return true;
			}
		}

		return false;
	}

	private function matchesResourceTemplate(string $uri): bool {
		foreach($this->resourceTemplates as $template) {
			$parts = preg_split('/(\{[^}]+\})/', $template, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
			$pattern = '';
			foreach($parts as $part) {
				$pattern .= str_starts_with($part, '{') && str_ends_with($part, '}')
					? '.+'
					: preg_quote($part, '/');
			}
			if($pattern !== '' && preg_match('/^' . $pattern . '$/u', $uri) === 1) {
				return true;
			}
		}

		return false;
	}

	/** @return array<int,array{name:string,arguments:array<string,mixed>}> */
	private function normalizeContextPromptEntries(IAgentContext $context): array {
		$result = [];

		foreach($this->contextPrompts as $entry) {
			$name = '';
			$arguments = [];

			if(is_string($entry)) {
				$name = trim($entry);
			}
			elseif(is_array($entry)) {
				$name = trim((string)($entry['name'] ?? ''));
				$arguments = is_array($entry['arguments'] ?? null) ? $entry['arguments'] : [];
			}

			if($name === '') {
				continue;
			}

			if($this->promptNames === []) {
				$this->getPromptDefinitions($context);
			}
			if(!isset($this->promptNames[$name])) {
				throw new \InvalidArgumentException('Unknown configured remote MCP prompt: ' . $name);
			}

			$result[] = ['name' => $name, 'arguments' => $arguments];
		}

		return $result;
	}

	private function resourceResultToText(array $result, string $requestedUri): string {
		$lines = [];
		$contents = is_array($result['contents'] ?? null) ? $result['contents'] : [];

		foreach($contents as $content) {
			if(!is_array($content)) {
				continue;
			}
			$uri = trim((string)($content['uri'] ?? $requestedUri));
			$mimeType = trim((string)($content['mimeType'] ?? ''));
			$text = (string)($content['text'] ?? '');
			if($text !== '') {
				$header = $uri !== '' ? 'Resource ' . $uri : 'Remote MCP resource';
				if($mimeType !== '') {
					$header .= ' (' . $mimeType . ')';
				}
				$lines[] = $header . ":\n" . $text;
				continue;
			}
			if(isset($content['blob'])) {
				$lines[] = 'Binary MCP resource omitted from text context: ' . ($uri !== '' ? $uri : $requestedUri)
					. ($mimeType !== '' ? ' (' . $mimeType . ')' : '');
			}
		}

		return trim(implode("\n\n", $lines));
	}

	private function promptResultToText(array $result, string $name): string {
		$lines = [];
		$description = trim((string)($result['description'] ?? ''));
		if($description !== '') {
			$lines[] = $description;
		}

		$messages = is_array($result['messages'] ?? null) ? $result['messages'] : [];
		foreach($messages as $message) {
			if(!is_array($message)) {
				continue;
			}
			$role = trim((string)($message['role'] ?? 'user'));
			$content = $this->contentToText($message['content'] ?? null);
			if($content !== '') {
				$lines[] = ($role !== '' ? $role : 'user') . ': ' . $content;
			}
		}

		if($lines === []) {
			throw new \RuntimeException('Remote MCP prompt returned no text content: ' . $name);
		}

		return implode("\n\n", $lines);
	}

	private function contentToText(mixed $content): string {
		if(is_string($content)) {
			return trim($content);
		}
		if(!is_array($content)) {
			return '';
		}
		if(isset($content['type'])) {
			$type = (string)$content['type'];
			if($type === 'text') {
				return trim((string)($content['text'] ?? ''));
			}
			if($type === 'resource' && is_array($content['resource'] ?? null)) {
				return trim((string)($content['resource']['text'] ?? ''));
			}
			return '[' . ($type !== '' ? $type : 'non-text') . ' MCP content omitted]';
		}

		$parts = [];
		foreach($content as $item) {
			$text = $this->contentToText($item);
			if($text !== '') {
				$parts[] = $text;
			}
		}

		return implode("\n", $parts);
	}

	private function redact(string $message): string {
		if($this->clientConfig instanceof McpClientConfig) {
			return $this->clientConfig->redactSensitiveText($message);
		}

		$token = (string)($this->resolvedClientConfig['token'] ?? '');
		return $token !== '' ? str_replace($token, '[redacted]', $message) : $message;
	}

}
