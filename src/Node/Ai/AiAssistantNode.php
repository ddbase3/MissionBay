<?php declare(strict_types=1);

namespace MissionBay\Node\Ai;

use Base3\Api\IAiChatModel;
use Base3\Logger\Api\ILogger;
use MissionBay\Api\IAgentContext;
use MissionBay\Agent\AgentNodeDock;
use MissionBay\Agent\AgentNodePort;
use MissionBay\Memory\IAgentMemory;
use MissionBay\Node\AbstractAgentNode;

class AiAssistantNode extends AbstractAgentNode {

	public static function getName(): string {
		return 'aiassistantnode';
	}

	public function getDescription(): string {
		return 'Sends a user prompt to a docked chat model and returns the assistant\'s response. Optionally includes system prompt and memory context.';
	}

	public function getInputDefinitions(): array {
		return [
			new AgentNodePort(
				name: 'prompt',
				description: 'The user\'s message to the assistant.',
				type: 'string',
				required: true
			),
			new AgentNodePort(
				name: 'system',
				description: 'Optional system message to guide assistant behavior.',
				type: 'string',
				default: 'You are a helpful assistant.',
				required: false
			)
		];
	}

	public function getOutputDefinitions(): array {
		return [
			new AgentNodePort(
				name: 'response',
				description: 'The assistant\'s reply.',
				type: 'string',
				required: false
			),
			new AgentNodePort(
				name: 'error',
				description: 'Error message, if any.',
				type: 'string',
				required: false
			)
		];
	}

	public function getDockDefinitions(): array {
		return [
			new AgentNodeDock(
				name: 'chatmodel',
				description: 'Docked assistant chat model.',
				interface: IAiChatModel::class,
				maxConnections: 1,
				required: true
			),
			new AgentNodeDock(
				name: 'memory',
				description: 'Optional memory for storing previous messages.',
				interface: IAgentMemory::class,
				maxConnections: 1,
				required: false
			),
			new AgentNodeDock(
				name: 'logger',
				description: 'Optional logger for events and errors.',
				interface: ILogger::class,
				maxConnections: 1,
				required: false
			)
		];
	}

	public function execute(array $inputs, array $resources, IAgentContext $context): array {
		/** @var IAiChatModel $model */
		$model = $resources['chatmodel'][0] ?? null;
		/** @var IAgentMemory|null $memory */
		$memory = $resources['memory'][0] ?? null;
		/** @var ILogger|null $logger */
		$logger = $resources['logger'][0] ?? null;

		$scope = 'aiassistant';

		if (!$model) {
			$msg = 'Missing required chat model.';
			if ($logger) $logger->log($scope, '[ERROR] ' . $msg);
			return ['error' => $this->error($msg)];
		}

		$prompt = trim($inputs['prompt'] ?? '');
		$system = trim($inputs['system'] ?? 'You are a helpful assistant.');

		if ($prompt === '') {
			$msg = 'Prompt is required.';
			if ($logger) $logger->log($scope, '[ERROR] ' . $msg);
			return ['error' => $this->error($msg)];
		}

		$messages = [['role' => 'system', 'content' => $system]];

		// Memory support
		$nodeId = $this->getId();
		if ($memory) {
			$history = $memory->loadNodeHistory($nodeId);
			foreach ($history as [$role, $text]) {
				$messages[] = ['role' => $role, 'content' => $text];
			}
		}

		$messages[] = ['role' => 'user', 'content' => $prompt];

		try {
			$response = $model->chat($messages);
			if ($memory) {
				$memory->appendNodeHistory($nodeId, 'user', $prompt);
				$memory->appendNodeHistory($nodeId, 'assistant', $response);
			}
			if ($logger) $logger->log($scope, 'Assistant responded with: ' . substr($response, 0, 80));
			return ['response' => $response];
		} catch (\Throwable $e) {
			$msg = 'Assistant call failed: ' . $e->getMessage();
			if ($logger) $logger->log($scope, '[ERROR] ' . $msg);
			return ['error' => $this->error($msg)];
		}
	}
}

