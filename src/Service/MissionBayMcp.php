<?php declare(strict_types=1);

namespace MissionBay\Service;

use Base3\Api\IOutput;
use Base3\Api\IClassMap;
use Base3\Api\IRequest;
use Base3\Logger\Api\ILogger;
use Base3\Token\Api\IToken;
use MissionBay\Api\IMcpAgent;

class MissionBayMcp implements IOutput {

	private const TOKEN_SCOPE = 'api';
	private const TOKEN_ID = 'missionbaymcpserver';

	public function __construct(
		private readonly IClassMap $classMap,
		private readonly IRequest $request,
		private readonly IToken $tokenService,
		private readonly ILogger $logger
	) {}

	public static function getName(): string {
		return 'missionbaymcp';
	}

	private function isAuthorized(): bool {
		$header = $_SERVER['HTTP_AUTHORIZATION']
			?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
			?? apache_request_headers()['Authorization'] ?? '';

		$this->logger->log('MCP', 'Auth check: ' . $header);

		if (str_starts_with(strtolower($header), 'bearer ')) {
			$token = trim(substr($header, 7));
			return $this->tokenService->check(self::TOKEN_SCOPE, self::TOKEN_ID, $token);
		}
		return false;
	}

	public function getOutput($out = "json") {
		if ($out !== "json") return 'This endpoint only supports JSON output.';

		$context = $this->request->getContext();
		$function = $this->request->get('function');

		// POST → Function Call
		if ($context === IRequest::CONTEXT_WEB_POST) {

			if (!$this->isAuthorized()) {
				http_response_code(401);
				return json_encode(['error' => 'Unauthorized'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
			}

			$agents = $this->classMap->getInstancesByInterface(IMcpAgent::class);

			foreach ($agents as $agent) {
				if ($agent->getFunctionName() === $function) {
					$input = $this->request->getJsonBody();
					if (!$input) $input = $this->request->allPost();
					$output = $agent->run($input);
					return json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
				}
			}

			http_response_code(404);
			return json_encode(['error' => 'Function not found: ' . $function], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
		}

		// Default → OpenAPI-Spec ausgeben
		$agents = $this->classMap->getInstancesByInterface(IMcpAgent::class);
		$paths = [];

		foreach ($agents as $agent) {
			$fn = $agent->getFunctionName();
			$inputSpec = $agent->getInputSpec();

			$properties = [];
			$required = [];
			foreach ($inputSpec as $key => $val) {
				$properties[$key] = [
					'type' => $val['type'] ?? 'string',
					'description' => $val['description'] ?? ''
				];
				if (!empty($val['required'])) {
					$required[] = $key;
				}
			}

			$schema = [
				'type' => 'object',
				'properties' => !empty($properties) ? $properties : new \stdClass()
			];
			if (!empty($required)) {
				$schema['required'] = $required;
			}

			$paths["/functions/$fn"] = [
				'post' => [
					'summary' => $agent->getDescription(),
					'operationId' => $fn,
					'requestBody' => [
						'required' => true,
						'content' => [
							'application/json' => [
								'schema' => $schema
							]
						]
					],
					'responses' => [
						'200' => [
							'description' => 'Successful response',
							'content' => [
								'application/json' => [
									'schema' => [
										'type' => 'object',
										'properties' => $agent->getOutputSpec()
									]
								]
							]
						]
					]
				]
			];
		}

		$openapi = [
			'openapi' => '3.1.0',
			'info' => [
				'title' => 'MissionBay MCP API',
				'version' => '1.0.0',
				'description' => 'OpenAI-compatible Agent Function Interface'
			],
			'servers' => [
				[ 'url' => 'https://agents.base3.de/mcp' ]
			],
			'x-debug-id' => uniqid('dbg_'),
			'paths' => $paths
		];

		return json_encode($openapi, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
	}

	public function getHelp(): string {
		return "Returns OpenAPI 3.1 spec or executes agent function for GPT function calling integration.";
	}
}

