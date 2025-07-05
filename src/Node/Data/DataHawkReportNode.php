<?php declare(strict_types=1);

namespace MissionBay\Node\Data;

use MissionBay\Api\IAgentContext;
use MissionBay\Agent\AgentNodePort;
use MissionBay\Node\AbstractAgentNode;
use DataHawk\Api\IReportExporterFactory;

class DataHawkReportNode extends AbstractAgentNode {

	private IReportExporterFactory $reportexporterfactory;

	public function __construct(IReportExporterFactory $reportexporterfactory) {
		$this->reportexporterfactory = $reportexporterfactory;
	}

	public static function getName(): string {
		return 'datahawkreportnode';
	}

	public function getInputDefinitions(): array {
		return [
			new AgentNodePort(
				name: 'config',
				description: 'Input configuration as JSON string, e.g., { "type": "table", "query": { ... } }',
				type: 'string',
				required: true
			)
		];
	}

	public function getOutputDefinitions(): array {
		return [
			new AgentNodePort(
				name: 'report',
				description: 'The generated report as string.',
				type: 'string',
				required: false
			),
			new AgentNodePort(
				name: 'columns',
				description: 'The column schema of the generated report.',
				type: 'array',
				required: false
			),
			new AgentNodePort(
				name: 'sql',
				description: 'The generated SQL statement.',
				type: 'string',
				required: false
			),
			new AgentNodePort(
				name: 'error',
				description: 'Error if report generation failed.',
				type: 'string',
				required: false
			)
		];
	}

	public function execute(array $inputs, IAgentContext $context): array {
		$configStr = $inputs['config'] ?? '';

		if (!is_string($configStr) || trim($configStr) === '') {
			return ['error' => $this->error('Missing or invalid config input')];
		}

		$config = json_decode($configStr, true);

		if (!is_array($config)) {
			return ['error' => $this->error('Could not parse config JSON')];
		}

		if (!isset($config['type']) || !isset($config['query'])) {
			return ['error' => $this->error('Missing "type" or "query" in config')];
		}

		try {
			$type = strtolower((string)$config['type']);
			$exporterType = match ($type) {
				'table' => 'htmltablereportexporter',
				default => 'htmltablereportexporter'
			};

			// TODO maybe better use reportqueryservice 
			$exporter = $this->reportexporterfactory->createExporter($exporterType);
			$report = $exporter->setExportQuery($config['query'])->toString();
			$sql = $exporter->toSql();

			$columns = null;
			$result = $exporter->getResult();
			if ($result != null) $columns = $result->columns;

			return ['report' => $report, 'sql' => $sql, 'columns' => $columns];
		} catch (\Throwable $e) {
			return ['error' => $this->error('Report generation failed: ' . $e->getMessage())];
		}
	}

	public function getDescription(): string {
		return 'Creates a report using DataHawk\'s exporter based on a JSON config string.';
	}
}

