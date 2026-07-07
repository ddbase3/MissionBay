<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 **********************************************************************/

namespace MissionBay\Mcp;

use Base3\Api\IClassMap;
use Base3\Api\IOutput;
use Base3\Api\IRequest;
use Base3\Logger\Api\ILogger;
use Base3\Settings\Api\ISettingsStore;
use MissionBay\Api\IAgentComponentPresetRepository;
use MissionBay\Api\IAgentContextFactory;

/**
 * Mcp
 *
 * Host-independent MCP HTTP endpoint for MissionBay tool profiles.
 */
class Mcp implements IOutput {

	private const LOG_SCOPE = 'missionbay_mcp';

	public function __construct(
		private readonly IRequest $request,
		private readonly IClassMap $classMap,
		private readonly ISettingsStore $settingsStore,
		private readonly IAgentComponentPresetRepository $presetRepository,
		private readonly IAgentContextFactory $contextFactory,
		private readonly ILogger $logger
	) {}

	public static function getName(): string {
		return 'mcp';
	}

	public function getOutput(string $out = 'json', bool $final = false): string {
		if($final) {
			header('Content-Type: application/json; charset=utf-8');
		}

		if(!$this->isPostRequest()) {
			if($final) {
				http_response_code(405);
				header('Allow: POST');
			}

			return $this->encode([
				'error' => 'Method not allowed. Use POST with MCP JSON-RPC payload.'
			]);
		}

		$profileId = trim((string)$this->request->get('profile', ''));

		if($profileId === '') {
			if($final) {
				http_response_code(400);
			}

			return $this->encode([
				'jsonrpc' => '2.0',
				'id' => null,
				'error' => [
					'code' => -32602,
					'message' => 'Missing MCP profile parameter.'
				]
			]);
		}

		try {
			$profile = $this->createProfileRepository()->getEnabledMcpProfile($profileId);
		}
		catch(\Throwable $e) {
			if($final) {
				http_response_code(404);
			}

			return $this->encode([
				'jsonrpc' => '2.0',
				'id' => null,
				'error' => [
					'code' => -32004,
					'message' => $e->getMessage()
				]
			]);
		}

		if(!$this->createAuthenticator()->isAuthorized($profile)) {
			return $this->unauthorizedResponse($final);
		}

		try {
			$payload = $this->readJsonPayload();
		}
		catch(\Throwable $e) {
			if($final) {
				http_response_code(400);
			}

			return $this->encode([
				'jsonrpc' => '2.0',
				'id' => null,
				'error' => [
					'code' => -32700,
					'message' => $e->getMessage()
				]
			]);
		}

		$handler = $this->createHandler();
		$responses = [];

		if($this->isList($payload)) {
			foreach($payload as $entry) {
				if(!is_array($entry)) {
					$responses[] = $this->jsonRpcError(null, -32600, 'Invalid Request.');
					continue;
				}

				$response = $handler->handle($entry, $profileId);

				if($response !== null) {
					$responses[] = $response;
				}
			}

			if($responses === []) {
				if($final) {
					http_response_code(202);
				}

				return '';
			}

			return $this->encode($responses);
		}

		if(!is_array($payload)) {
			if($final) {
				http_response_code(400);
			}

			return $this->encode($this->jsonRpcError(null, -32600, 'Invalid Request.'));
		}

		$response = $handler->handle($payload, $profileId);

		if($response === null) {
			if($final) {
				http_response_code(202);
			}

			return '';
		}

		return $this->encode($response);
	}


	private function unauthorizedResponse(bool $final): string {
		$response = $this->encode([
			'jsonrpc' => '2.0',
			'id' => null,
			'error' => [
				'code' => -32001,
				'message' => 'Unauthorized'
			]
		]);

		if($final) {
			http_response_code(401);
			header('WWW-Authenticate: Bearer');
			header('Content-Length: ' . strlen($response));
		}

		return $response;
	}

	private function createAuthenticator(): McpBearerAuthenticator {
		return new McpBearerAuthenticator($this->logger);
	}

	private function createProfileRepository(): McpToolProfileRepository {
		return new McpToolProfileRepository($this->settingsStore);
	}

	private function createHandler(): McpJsonRpcHandler {
		$profileRepository = $this->createProfileRepository();
		$definitionMapper = new McpToolDefinitionMapper();
		$resultMapper = new McpToolResultMapper();
		$materializer = new McpToolPresetMaterializer(
			$this->presetRepository,
			$this->classMap,
			$this->contextFactory,
			$this->logger
		);

		return new McpJsonRpcHandler(
			$profileRepository,
			$materializer,
			$definitionMapper,
			$resultMapper,
			$this->logger
		);
	}

	private function isPostRequest(): bool {
		$method = strtoupper((string)$this->request->server('REQUEST_METHOD', ''));

		if($method !== '') {
			return $method === 'POST';
		}

		return $this->request->getContext() === IRequest::CONTEXT_WEB_POST;
	}

	/**
	 * @return mixed
	 */
	private function readJsonPayload(): mixed {
		$payload = $this->request->getJsonBody();

		if($payload !== []) {
			return $payload;
		}

		$raw = file_get_contents('php://input');

		if(!is_string($raw) || trim($raw) === '') {
			throw new \RuntimeException('Empty JSON-RPC request body.');
		}

		return $this->decodeJsonPayload($raw);
	}

	/**
	 * @return mixed
	 */
	private function decodeJsonPayload(string $raw): mixed {
		$decoded = json_decode($raw, true);

		if(json_last_error() !== JSON_ERROR_NONE) {
			throw new \RuntimeException('Parse error: ' . json_last_error_msg());
		}

		return $decoded;
	}

	/**
	 * @param mixed $value
	 */
	private function isList(mixed $value): bool {
		if(!is_array($value)) {
			return false;
		}

		if(function_exists('array_is_list')) {
			return array_is_list($value);
		}

		return array_keys($value) === range(0, count($value) - 1);
	}

	/**
	 * @return array<string,mixed>
	 */
	private function jsonRpcError(mixed $id, int $code, string $message): array {
		return [
			'jsonrpc' => '2.0',
			'id' => $id,
			'error' => [
				'code' => $code,
				'message' => $message
			]
		];
	}

	private function encode(mixed $data): string {
		$json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

		if(!is_string($json)) {
			$this->logger->logLevel(ILogger::ERROR, 'Failed to encode MCP response.', [
				'scope' => self::LOG_SCOPE,
				'error' => json_last_error_msg()
			]);

			return '{"error":"Failed to encode MCP response."}';
		}

		return $json;
	}
}
