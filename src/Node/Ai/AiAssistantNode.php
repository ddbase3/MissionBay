<?php declare(strict_types=1);

namespace MissionBay\Node\Ai;

use AssistantFoundation\Api\IAiChatModel;
use Base3\Logger\Api\ILogger;
use MissionBay\Api\IAgentContext;
use MissionBay\Api\IAgentFlow;
use MissionBay\Api\IAgentMemory;
use MissionBay\Api\IAgentTool;
use MissionBay\Agent\AgentNodeDock;
use MissionBay\Agent\AgentNodePort;
use MissionBay\Node\AbstractAgentNode;

class AiAssistantNode extends AbstractAgentNode {

	protected ?ILogger $logger = null;

	public static function getName(): string {
		return 'aiassistantnode';
	}

	public function getDescription(): string {
		return 'Sends a user prompt to a docked chat model and returns the assistant response.';
	}

	public function getInputDefinitions(): array {
		return [
			new AgentNodePort(
				name: 'prompt',
				description: 'User message',
				type: 'string',
				default: null,
				required: true
			),
			new AgentNodePort(
				name: 'system',
				description: 'System message',
				type: 'string',
				default: 'You are a helpful assistant.',
				required: false
			)
		];
	}

	public function getOutputDefinitions(): array {
		return [
			new AgentNodePort(
				name: 'message',
				description: 'Assistant message',
				type: 'array',
				default: null,
				required: false
			),
			new AgentNodePort(
				name: 'tool_calls',
				description: 'Tool calls executed',
				type: 'array',
				default: [],
				required: false
			),
			new AgentNodePort(
				name: 'error',
				description: 'Error message',
				type: 'string',
				default: null,
				required: false
			)
		];
	}

	public function getDockDefinitions(): array {
		return [
			new AgentNodeDock(
				name: 'chatmodel',
				description: 'Assistant model',
				interface: IAiChatModel::class,
				maxConnections: 1,
				required: true
			),
			new AgentNodeDock(
				name: 'memory',
				description: 'Memory providers',
				interface: IAgentMemory::class,
				maxConnections: 99,
				required: false
			),
			new AgentNodeDock(
				name: 'logger',
				description: 'Optional logger',
				interface: ILogger::class,
				maxConnections: 1,
				required: false
			),
			new AgentNodeDock(
				name: 'tools',
				description: 'Assistant tools',
				interface: IAgentTool::class,
				maxConnections: 99,
				required: false
			)
		];
	}

	public function execute(array $inputs, array $resources, IAgentContext $context, IAgentFlow $flow): array {

		$model = $resources['chatmodel'][0] ?? null;
		$memories = $resources['memory'] ?? [];
		$tools = $resources['tools'] ?? [];

		if (isset($resources['logger'][0])) {
			$this->logger = $resources['logger'][0];
		}

		if (!$model) {
			return ['error' => 'Missing required model'];
		}

		// sort memories
		usort($memories, fn(IAgentMemory $a, IAgentMemory $b) => $a->getPriority() <=> $b->getPriority());

		$prompt = trim($inputs['prompt'] ?? '');
		$system = trim($inputs['system'] ?? '');

		if ($prompt === '') {
			return ['error' => 'Prompt is required'];
		}

		$messages = [['role' => 'system', 'content' => $system]];

		$nodeId = $this->getId();

		foreach ($memories as $memory) {
			$history = $memory->loadNodeHistory($nodeId);
			foreach ($history as $entry) {
				$messages[] = $entry;
			}
		}

		$userMessage = [
			'id'        => uniqid('msg_', true),
			'role'      => 'user',
			'content'   => $prompt,
			'timestamp' => (new \DateTimeImmutable())->format('c'),
			'feedback'  => null
		];

		$messages[] = $userMessage;

		$toolDefs = [];
		foreach ($tools as $tool) {
			foreach ($tool->getToolDefinitions() as $def) {
				$toolDefs[] = $def;
			}
		}

		$toolCalls = [];
		$assistantMessage = null;

		$loopGuard = 0;
		$maxLoops = 5;

		while ($loopGuard++ < $maxLoops) {

			// LLM START
			$flow->emitEvent([
				'type'    => 'status_update',
				'stage'   => 'llm_start',
				'message' => 'Calling assistant model...'
			]);

			$result = $model->raw($messages, $toolDefs);

			// LLM END
			$flow->emitEvent([
				'type'    => 'status_update',
				'stage'   => 'llm_end',
				'message' => 'Model returned response.'
			]);

			$message = $result['choices'][0]['message'] ?? null;
			if (!$message) {
				return ['error' => 'Malformed LLM response'];
			}

			$messages[] = $message;

			if (!empty($message['tool_calls'])) {

				foreach ($message['tool_calls'] as $call) {

					$toolName = $call['function']['name'] ?? '';
					$args = json_decode($call['function']['arguments'] ?? '{}', true) ?? [];

					// TOOL START
					$flow->emitEvent([
						'type'    => 'status_update',
						'stage'   => 'tool_start',
						'message' => "Calling tool: $toolName..."
					]);

					$toolObj = $this->findToolByName($tools, $toolName);
					$result = $toolObj ? $toolObj->callTool($toolName, $args, $context) : null;

					// TOOL END
					$flow->emitEvent([
						'type'    => 'status_update',
						'stage'   => 'tool_end',
						'message' => "Tool finished: $toolName."
					]);

					$toolCalls[] = [
						'tool' => $toolName,
						'arguments' => $args,
						'result' => $result
					];

					$messages[] = [
						'role' => 'tool',
						'tool_call_id' => $call['id'],
						'content' => json_encode($result)
					];
				}

				continue;
			}

			$assistantMessage = [
				'id'        => uniqid('msg_', true),
				'role'      => 'assistant',
				'content'   => $message['content'] ?? '',
				'timestamp' => (new \DateTimeImmutable())->format('c'),
				'feedback'  => null
			];

			break;
		}

		foreach ($memories as $memory) {
			$memory->appendNodeHistory($nodeId, $userMessage);
			if ($assistantMessage) {
				$memory->appendNodeHistory($nodeId, $assistantMessage);
			}
		}

		return [
			'message'    => $assistantMessage,
			'tool_calls' => $toolCalls
		];
	}

	private function findToolByName(array $tools, string $name): ?IAgentTool {
		foreach ($tools as $tool) {
			foreach ($tool->getToolDefinitions() as $def) {
				if (($def['function']['name'] ?? '') === $name) {
					return $tool;
				}
			}
		}
		return null;
	}
}
