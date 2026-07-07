<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 **********************************************************************/

namespace MissionBay\Mcp;

use Base3\Logger\Api\ILogger;

/**
 * McpJsonRpcHandler
 *
 * Minimal JSON-RPC handler for MCP initialize, tools/list and tools/call.
 */
class McpJsonRpcHandler {

	private const LOG_SCOPE = 'missionbay_mcp';
	private const PROTOCOL_VERSION = '2025-06-18';

	public function __construct(
		private readonly McpToolProfileRepository $profileRepository,
		private readonly McpToolPresetMaterializer $materializer,
		private readonly McpToolDefinitionMapper $definitionMapper,
		private readonly McpToolResultMapper $resultMapper,
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

		if($method === '') {
			return $this->error($id, -32600, 'Invalid Request: missing method.');
		}

		if(str_starts_with($method, 'notifications/')) {
			return null;
		}

		try {
			return match($method) {
				'initialize' => $this->response($id, $this->initialize($request)),
				'tools/list' => $this->response($id, $this->listTools($profileId)),
				'tools/call' => $this->response($id, $this->callTool($profileId, $request)),
				default => $this->error($id, -32601, 'Method not found: ' . $method)
			};
		}
		catch(\InvalidArgumentException $e) {
			return $this->error($id, -32602, $e->getMessage());
		}
		catch(\Throwable $e) {
			$this->logger->logLevel(ILogger::ERROR, 'MCP JSON-RPC request failed.', [
				'scope' => self::LOG_SCOPE,
				'method' => $method,
				'error' => $e->getMessage()
			]);

			return $this->error($id, -32603, $e->getMessage());
		}
	}

	/**
	 * @param array<string,mixed> $request
	 * @return array<string,mixed>
	 */
	private function initialize(array $request): array {
		$params = is_array($request['params'] ?? null) ? $request['params'] : [];
		$protocolVersion = trim((string)($params['protocolVersion'] ?? ''));

		return [
			'protocolVersion' => $protocolVersion !== '' ? $protocolVersion : self::PROTOCOL_VERSION,
			'capabilities' => [
				'tools' => [
					'listChanged' => false
				]
			],
			'serverInfo' => [
				'name' => 'MissionBay MCP',
				'version' => '0.1.0'
			]
		];
	}

	/**
	 * @return array<string,mixed>
	 */
	private function listTools(string $profileId): array {
		$profile = $this->profileRepository->getEnabledMcpProfile($profileId);
		$context = $this->materializer->createContext($profile);
		$tools = $this->materializer->materialize($profile, $context);
		$catalog = new McpToolCatalog($tools, $this->definitionMapper, $this->logger);

		return [
			'tools' => $catalog->listTools()
		];
	}

	/**
	 * @param array<string,mixed> $request
	 * @return array<string,mixed>
	 */
	private function callTool(string $profileId, array $request): array {
		$params = is_array($request['params'] ?? null) ? $request['params'] : [];
		$name = trim((string)($params['name'] ?? ''));

		if($name === '') {
			throw new \InvalidArgumentException('Missing tools/call parameter: name.');
		}

		$arguments = $params['arguments'] ?? [];

		if(!is_array($arguments)) {
			throw new \InvalidArgumentException('Invalid tools/call parameter: arguments must be an object.');
		}

		$profile = $this->profileRepository->getEnabledMcpProfile($profileId);
		$context = $this->materializer->createContext($profile);
		$tools = $this->materializer->materialize($profile, $context);
		$catalog = new McpToolCatalog($tools, $this->definitionMapper, $this->logger);

		try {
			return $this->resultMapper->success($catalog->call($name, $arguments, $context));
		}
		catch(\Throwable $e) {
			$this->logger->logLevel(ILogger::ERROR, 'MCP tool call failed.', [
				'scope' => self::LOG_SCOPE,
				'tool' => $name,
				'error' => $e->getMessage()
			]);

			return $this->resultMapper->error($e->getMessage());
		}
	}

	/**
	 * @return array<string,mixed>
	 */
	private function response(mixed $id, array $result): array {
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
}
