<?php declare(strict_types=1);

namespace MissionBay\Service;

use Base3\Api\IOutput;
use Base3\Api\IClassMap;
use Base3\Api\IRequest;
use Base3\Logger\Api\ILogger;
use MissionBay\Api\IMcpAgent;
use MissionBay\Api\IAgentContextFactory;

class MissionBayMcp implements IOutput {

	public function __construct(
		private readonly IClassMap $classMap,
		private readonly IRequest $request,
		private readonly ILogger $logger,
		private readonly IAgentContextFactory $agentcontextfactory
	) {}

	public static function getName(): string {
		return 'missionbaymcp';
	}

	public function getOutput($out = "html") {
		if ($out !== "json") return 'This is a JSON endpoint only';

		$context = $this->agentcontextfactory->createContext();
		$agents = $this->classMap->getInstancesByInterface(IMcpAgent::class);

		if ($this->request->getContext() === IRequest::CONTEXT_WEB_GET) {
			$data = [];
			foreach ($agents as $agent) {
				$data[] = [
					'name' => $agent->getFunctionName(),
					'description' => $agent->getDescription(),
					'parameters' => [
						'type' => 'object',
						'properties' => array_map(function ($def) {
							return [
								'type' => $def['type'] ?? 'string',
								'description' => $def['description'] ?? '',
							];
						}, $agent->getInputSpec()),
						'required' => array_keys(array_filter($agent->getInputSpec(), fn($def) => ($def['required'] ?? false)))
					],
					'category' => $agent->getCategory(),
					'tags' => $agent->getTags(),
					'version' => $agent->getVersion(),
				];
			}
			return json_encode(['functions' => $data], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
		}

		if ($this->request->getContext() === IRequest::CONTEXT_WEB_POST) {
			$raw = file_get_contents("php://input");
			$post = json_decode($raw, true);
			$function = $post['function'] ?? null;
			$inputs = $post['inputs'] ?? ($post['parameters'] ?? []);

			foreach ($agents as $agent) {
				if ($agent->getFunctionName() === $function) {
					$agent->setId(uniqid("agent_"));
					$agent->setContext($context);

					$this->logger->log("MCP", "Calling agent function '$function' with inputs: " . json_encode($inputs));
					$output = $agent->run($inputs);
					$this->logger->log("MCP", "Agent '$function' returned: " . json_encode($output));

					return json_encode([
						'function' => $function,
						'output' => $output
					], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
				}
			}

			return json_encode([
				'error' => "Unknown function: $function"
			], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
		}

		return json_encode([
			'error' => 'Unsupported request context'
		], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
	}

	public function getHelp(): string {
		return "'GET' => OpenAI-compatible list of available functions with JSON Schema.\n"
			. "'POST' => { function: string, inputs: { ... } } or { name: string, parameters: { ... } }";
	}
}

