<?php declare(strict_types=1);

namespace MissionBay\Service;

use Base3\Api\IOutput;
use Base3\Api\IClassMap;
use MissionBay\Api\IMcpAgent;

class MissionBayMcpOpenApi implements IOutput {

	private IClassMap $classMap;

	public function __construct(IClassMap $classMap) {
		$this->classMap = $classMap;
	}

	public static function getName(): string {
		return 'missionbaymcpopenapi';
	}

	public function getOutput($out = "json") {
		if ($out !== "json") return 'This endpoint only supports JSON output.';

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

			$paths["/functions/$fn"] = [
				'post' => [
					'summary' => $agent->getDescription(),
					'operationId' => $fn,
					'requestBody' => [
						'required' => true,
						'content' => [
							'application/json' => [
								'schema' => [
									'type' => 'object',
									'properties' => $properties,
									'required' => $required
								]
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
				[ 'url' => 'https://agents.base3.de/missionbaymcp.json' ]
			],
			'paths' => $paths
		];

		return json_encode($openapi, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
	}

	public function getHelp(): string {
		return "Returns OpenAPI 3.1 spec for GPT function calling integration.";
	}
}

