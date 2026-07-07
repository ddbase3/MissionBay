<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 **********************************************************************/

namespace MissionBay\Mcp;

use Base3\Api\IClassMap;
use Base3\Logger\Api\ILogger;
use MissionBay\Api\IAgentResourceProvider;
use MissionBay\Api\IAgentPromptProvider;

/**
 * McpJsonRpcHandler
 *
 * Handles the JSON-RPC layer for the MissionBay MCP endpoint. HTTP-level
 * checks are handled by McpHttpGuard before requests reach this class.
 */
class McpJsonRpcHandler {

	private const LOG_SCOPE = 'missionbay_mcp';
	private const PROTOCOL_VERSION = '2025-06-18';
	private const PAGE_SIZE = 50;

	/**
	 * @var array<int,string>
	 */
	private const SUPPORTED_PROTOCOL_VERSIONS = [
		'2025-06-18',
		'2025-03-26'
	];

	public function __construct(
		private readonly McpToolProfileRepository $profileRepository,
		private readonly McpToolPresetMaterializer $materializer,
		private readonly McpToolDefinitionMapper $definitionMapper,
		private readonly McpToolResultMapper $resultMapper,
		private readonly McpConfirmationService $confirmationService,
		private readonly IClassMap $classMap,
		private readonly ILogger $logger
	) {}

	public static function getName(): string {
		return 'mcpjsonrpchandler';
	}

	/**
	 * @param array<string,mixed> $request
	 * @return array<string,mixed>|null
	 */
	public function handle(array $request, string $profileId): ?array {
		$id = $request['id'] ?? null;
		$method = trim((string)($request['method'] ?? ''));
		$startedAt = microtime(true);

		if($method === '') {
			return $this->error($id, -32600, 'Invalid Request: missing method.');
		}

		if(str_starts_with($method, 'notifications/')) {
			$this->handleNotification($request, $profileId, $method);
			return null;
		}

		$this->logger->logLevel(ILogger::INFO, 'MCP JSON-RPC request started.', [
			'scope' => self::LOG_SCOPE,
			'profile' => $profileId,
			'method' => $method
		]);

		try {
			$response = match($method) {
				'initialize' => $this->response($id, $this->initialize($request)),
				'ping' => $this->response($id, $this->ping()),
				'tools/list' => $this->response($id, $this->listTools($profileId, $request)),
				'tools/call' => $this->response($id, $this->callTool($profileId, $request)),
				'resources/list' => $this->response($id, $this->listResources($profileId, $request)),
				'resources/read' => $this->response($id, $this->readResource($profileId, $request)),
				'resources/templates/list' => $this->response($id, $this->listResourceTemplates($profileId, $request)),
				'prompts/list' => $this->response($id, $this->listPrompts($profileId, $request)),
				'prompts/get' => $this->response($id, $this->getPrompt($profileId, $request)),
				default => $this->error($id, -32601, 'Method not found: ' . $method)
			};

			$this->logger->logLevel(ILogger::INFO, 'MCP JSON-RPC request finished.', [
				'scope' => self::LOG_SCOPE,
				'profile' => $profileId,
				'method' => $method,
				'ok' => !isset($response['error']),
				'duration_ms' => $this->durationMs($startedAt)
			]);

			return $response;
		}
		catch(\InvalidArgumentException $e) {
			$this->logger->logLevel(ILogger::WARNING, 'MCP JSON-RPC request rejected.', [
				'scope' => self::LOG_SCOPE,
				'profile' => $profileId,
				'method' => $method,
				'error' => $e->getMessage(),
				'duration_ms' => $this->durationMs($startedAt)
			]);

			return $this->error($id, -32602, $e->getMessage());
		}
		catch(\Throwable $e) {
			$this->logger->logLevel(ILogger::ERROR, 'MCP JSON-RPC request failed.', [
				'scope' => self::LOG_SCOPE,
				'profile' => $profileId,
				'method' => $method,
				'error' => $e->getMessage(),
				'duration_ms' => $this->durationMs($startedAt)
			]);

			return $this->error($id, -32603, $e->getMessage());
		}
	}

	/**
	 * @param array<string,mixed> $request
	 */
	private function handleNotification(array $request, string $profileId, string $method): void {
		$params = is_array($request['params'] ?? null) ? $request['params'] : [];

		if($method === 'notifications/cancelled') {
			$this->logger->logLevel(ILogger::INFO, 'Accepted MCP cancellation notification.', [
				'scope' => self::LOG_SCOPE,
				'profile' => $profileId,
				'method' => $method,
				'request_id' => $params['requestId'] ?? null,
				'reason' => $params['reason'] ?? null
			]);
			return;
		}

		$this->logger->logLevel(ILogger::DEBUG, 'Accepted MCP notification.', [
			'scope' => self::LOG_SCOPE,
			'profile' => $profileId,
			'method' => $method
		]);
	}

	private function ping(): object {
		return (object)[];
	}

	/**
	 * @param array<string,mixed> $request
	 * @return array<string,mixed>
	 */
	private function initialize(array $request): array {
		$params = is_array($request['params'] ?? null) ? $request['params'] : [];
		$protocolVersion = trim((string)($params['protocolVersion'] ?? ''));

		if($protocolVersion !== '' && !in_array($protocolVersion, self::SUPPORTED_PROTOCOL_VERSIONS, true)) {
			throw new \InvalidArgumentException('Unsupported MCP protocol version: ' . $protocolVersion);
		}

		return [
			'protocolVersion' => $protocolVersion !== '' ? $protocolVersion : self::PROTOCOL_VERSION,
			'capabilities' => [
				'tools' => [
					'listChanged' => false
				],
				'resources' => [
					'subscribe' => false,
					'listChanged' => false
				],
				'prompts' => [
					'listChanged' => false
				]
			],
			'serverInfo' => [
				'name' => 'MissionBay MCP',
				'version' => '1.0.0'
			]
		];
	}

	/**
	 * @param array<string,mixed> $request
	 * @return array<string,mixed>
	 */
	private function listTools(string $profileId, array $request): array {
		$params = is_array($request['params'] ?? null) ? $request['params'] : [];
		$cursor = $this->getCursor($params);
		$profile = $this->profileRepository->getEnabledMcpProfile($profileId);
		$context = $this->materializer->createContext($profile);
		$tools = $this->materializer->materialize($profile, $context);
		$catalog = new McpToolCatalog($tools, $this->definitionMapper, $this->logger);

		$tools = $catalog->listTools();
		array_unshift($tools, $this->definitionMapper->toMcpTool($this->confirmationService->getToolDefinition()));

		return $this->paginate('tools', $tools, $cursor, 'Invalid tools/list cursor.');
	}


	/**
	 * @param array<string,mixed> $request
	 * @return array<string,mixed>
	 */
	private function listResources(string $profileId, array $request): array {
		$params = is_array($request['params'] ?? null) ? $request['params'] : [];
		$cursor = isset($params['cursor']) && is_scalar($params['cursor']) ? trim((string)$params['cursor']) : null;

		return $this->createResourceCatalog($profileId)->listResources($cursor);
	}

	/**
	 * @param array<string,mixed> $request
	 * @return array<string,mixed>
	 */
	private function readResource(string $profileId, array $request): array {
		$params = is_array($request['params'] ?? null) ? $request['params'] : [];
		$uri = trim((string)($params['uri'] ?? ''));

		return $this->createResourceCatalog($profileId)->readResource($uri);
	}


	/**
	 * @param array<string,mixed> $request
	 * @return array<string,mixed>
	 */
	private function listResourceTemplates(string $profileId, array $request): array {
		$params = is_array($request['params'] ?? null) ? $request['params'] : [];
		$cursor = $this->getCursor($params);

		return $this->createResourceCatalog($profileId)->listResourceTemplates($cursor);
	}

	private function createResourceCatalog(string $profileId): McpResourceCatalog {
		$profile = $this->profileRepository->getEnabledMcpProfile($profileId);
		$context = $this->materializer->createContext($profile);
		$tools = $this->materializer->materialize($profile, $context);
		$providers = [new McpProfileResourceProvider($profile)];

		foreach(McpHostProviderRegistry::getResourceProviders() as $provider) {
			$providers[] = $provider;
		}

		foreach($this->getGlobalResourceProviders() as $provider) {
			$providers[] = $provider;
		}

		foreach($tools as $tool) {
			if($tool instanceof IAgentResourceProvider) {
				$providers[] = $tool;
			}
		}

		return new McpResourceCatalog($providers, $context, $this->logger);
	}


	/**
	 * @return IAgentResourceProvider[]
	 */
	private function getGlobalResourceProviders(): array {
		try {
			return $this->classMap->getInstancesByInterface(IAgentResourceProvider::class);
		}
		catch(\Throwable $e) {
			$this->logger->logLevel(ILogger::WARNING, 'Failed to load global MCP resource providers.', [
				'scope' => self::LOG_SCOPE,
				'error' => $e->getMessage()
			]);

			return [];
		}
	}


	/**
	 * @param array<string,mixed> $request
	 * @return array<string,mixed>
	 */
	private function listPrompts(string $profileId, array $request): array {
		$params = is_array($request['params'] ?? null) ? $request['params'] : [];
		$cursor = isset($params['cursor']) && is_scalar($params['cursor']) ? trim((string)$params['cursor']) : null;

		return $this->createPromptCatalog($profileId)->listPrompts($cursor);
	}

	/**
	 * @param array<string,mixed> $request
	 * @return array<string,mixed>
	 */
	private function getPrompt(string $profileId, array $request): array {
		$params = is_array($request['params'] ?? null) ? $request['params'] : [];
		$name = trim((string)($params['name'] ?? ''));
		$arguments = $params['arguments'] ?? [];

		if(!is_array($arguments)) {
			throw new \InvalidArgumentException('Invalid prompts/get parameter: arguments must be an object.');
		}

		return $this->createPromptCatalog($profileId)->getPrompt($name, $arguments);
	}

	private function createPromptCatalog(string $profileId): McpPromptCatalog {
		$profile = $this->profileRepository->getEnabledMcpProfile($profileId);
		$context = $this->materializer->createContext($profile);
		$tools = $this->materializer->materialize($profile, $context);
		$providers = [];

		foreach(McpHostProviderRegistry::getPromptProviders() as $provider) {
			$providers[] = $provider;
		}

		foreach($this->getGlobalPromptProviders() as $provider) {
			$providers[] = $provider;
		}

		foreach($tools as $tool) {
			if($tool instanceof IAgentPromptProvider) {
				$providers[] = $tool;
			}
		}

		return new McpPromptCatalog($providers, $context, $this->logger);
	}


	/**
	 * @return IAgentPromptProvider[]
	 */
	private function getGlobalPromptProviders(): array {
		try {
			return $this->classMap->getInstancesByInterface(IAgentPromptProvider::class);
		}
		catch(\Throwable $e) {
			$this->logger->logLevel(ILogger::WARNING, 'Failed to load global MCP prompt providers.', [
				'scope' => self::LOG_SCOPE,
				'error' => $e->getMessage()
			]);

			return [];
		}
	}


	/**
	 * @param array<string,mixed> $request
	 * @return array<string,mixed>
	 */
	private function callTool(string $profileId, array $request): array {
		$params = is_array($request['params'] ?? null) ? $request['params'] : [];
		$name = trim((string)($params['name'] ?? ''));
		$startedAt = microtime(true);

		if($name === '') {
			throw new \InvalidArgumentException('Missing tools/call parameter: name.');
		}

		$arguments = $params['arguments'] ?? [];

		if(!is_array($arguments)) {
			throw new \InvalidArgumentException('Invalid tools/call parameter: arguments must be an object.');
		}

		$this->logger->logLevel(ILogger::INFO, 'MCP tool call started.', [
			'scope' => self::LOG_SCOPE,
			'profile' => $profileId,
			'tool' => $name
		]);

		$profile = $this->profileRepository->getEnabledMcpProfile($profileId);
		$context = $this->materializer->createContext($profile);
		$tools = $this->materializer->materialize($profile, $context);
		$catalog = new McpToolCatalog($tools, $this->definitionMapper, $this->logger);

		try {
			if($name === McpConfirmationService::TOOL_NAME) {
				$result = $this->resultMapper->success(
					$this->confirmationService->handleConfirmationTool($profileId, $arguments, $catalog, $context)
				);
			}
			else {
				$pendingConfirmation = $this->confirmationService->createPendingIfNeeded($profileId, $name, $arguments, $catalog, $context);

				$result = $this->resultMapper->success(
					$pendingConfirmation ?? $catalog->call($name, $arguments, $context)
				);
			}

			$this->logger->logLevel(ILogger::INFO, 'MCP tool call finished.', [
				'scope' => self::LOG_SCOPE,
				'profile' => $profileId,
				'tool' => $name,
				'ok' => true,
				'duration_ms' => $this->durationMs($startedAt)
			]);

			return $result;
		}
		catch(\Throwable $e) {
			$this->logger->logLevel(ILogger::ERROR, 'MCP tool call failed.', [
				'scope' => self::LOG_SCOPE,
				'profile' => $profileId,
				'tool' => $name,
				'error' => $e->getMessage(),
				'duration_ms' => $this->durationMs($startedAt)
			]);

			return $this->resultMapper->error($e->getMessage());
		}
	}


	/**
	 * @param array<string,mixed> $params
	 */
	private function getCursor(array $params): ?string {
		if(!isset($params['cursor']) || !is_scalar($params['cursor'])) {
			return null;
		}

		$cursor = trim((string)$params['cursor']);

		return $cursor !== '' ? $cursor : null;
	}

	/**
	 * @param array<int,array<string,mixed>> $items
	 * @return array<string,mixed>
	 */
	private function paginate(string $key, array $items, ?string $cursor, string $errorMessage): array {
		$offset = $this->decodeCursor($cursor, $errorMessage);
		$page = array_slice($items, $offset, self::PAGE_SIZE);
		$result = [
			$key => $page
		];

		$nextOffset = $offset + self::PAGE_SIZE;

		if($nextOffset < count($items)) {
			$result['nextCursor'] = (string)$nextOffset;
		}

		return $result;
	}

	private function decodeCursor(?string $cursor, string $errorMessage): int {
		if($cursor === null || trim($cursor) === '') {
			return 0;
		}

		if(!ctype_digit($cursor)) {
			throw new \InvalidArgumentException($errorMessage);
		}

		return max(0, (int)$cursor);
	}

	/**
	 * @param mixed $result
	 * @return array<string,mixed>
	 */
	private function response(mixed $id, mixed $result): array {
		return [
			'jsonrpc' => '2.0',
			'id' => $id,
			'result' => $result
		];
	}

	/**
	 * @return array<string,mixed>
	 */
	private function error(mixed $id, int $code, string $message): array {
		return [
			'jsonrpc' => '2.0',
			'id' => $id,
			'error' => [
				'code' => $code,
				'message' => $message
			]
		];
	}

	private function durationMs(float $startedAt): int {
		return (int)round((microtime(true) - $startedAt) * 1000);
	}
}
