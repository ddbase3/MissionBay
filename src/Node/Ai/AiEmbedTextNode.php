<?php declare(strict_types=1);

namespace MissionBay\Node\Ai;

use AssistantFoundation\Api\IAiEmbeddingModel;
use Base3\Logger\Api\ILogger;
use MissionBay\Api\IAgentContext;
use MissionBay\Api\IAgentFlow;
use MissionBay\Agent\AgentNodeDock;
use MissionBay\Agent\AgentNodePort;
use MissionBay\Node\AbstractAgentNode;

class AiEmbedTextNode extends AbstractAgentNode {

	public static function getName(): string {
		return 'aiembedtextnode';
	}

	public function getDescription(): string {
		return 'Encodes one or more input texts into embeddings using a docked embedding model resource.';
	}

	public function getInputDefinitions(): array {
		return [
			new AgentNodePort(
				name: 'texts',
				description: 'Single string or array of texts to be embedded.',
				type: 'mixed', // supports string or array of strings
				required: true
			)
		];
	}

	public function getOutputDefinitions(): array {
		return [
			new AgentNodePort(
				name: 'embeddings',
				description: 'Array of embedding vectors (float arrays).',
				type: 'array',
				required: false
			),
			new AgentNodePort(
				name: 'error',
				description: 'Error message if embedding failed.',
				type: 'string',
				required: false
			)
		];
	}

	public function getDockDefinitions(): array {
		return [
			new AgentNodeDock(
				name: 'embeddingmodel',
				description: 'The embedding model to use.',
				interface: IAiEmbeddingModel::class,
				maxConnections: 1,
				required: true
			),
			new AgentNodeDock(
				name: 'logger',
				description: 'Optional logger for diagnostics and errors.',
				interface: ILogger::class,
				maxConnections: 1,
				required: false
			)
		];
	}

	public function execute(array $inputs, array $resources, IAgentContext $context, IAgentFlow $flow): array {
		/** @var IAiEmbeddingModel $model */
		$model = $resources['embeddingmodel'][0] ?? null;
		/** @var ILogger|null $logger */
		$logger = $resources['logger'][0] ?? null;
		$scope = 'aiembed';

		if (!$model) {
			$msg = 'Missing embedding model resource.';
			if ($logger) $logger->log($scope, "[ERROR] $msg");
			return ['error' => $this->error($msg)];
		}

		$texts = $inputs['texts'] ?? null;

		if (is_string($texts)) {
			$texts = [$texts];
		} elseif (!is_array($texts)) {
			$msg = 'Input "texts" must be a string or array of strings.';
			if ($logger) $logger->log($scope, "[ERROR] $msg");
			return ['error' => $this->error($msg)];
		}

		try {
			$embeddings = $model->embed($texts);
			if ($logger) $logger->log($scope, "Embedding completed for " . count($texts) . " text(s).");
			return ['embeddings' => $embeddings];
		} catch (\Throwable $e) {
			$msg = 'Embedding failed: ' . $e->getMessage();
			if ($logger) $logger->log($scope, "[ERROR] $msg");
			return ['error' => $this->error($msg)];
		}
	}
}

