<?php declare(strict_types=1);

namespace MissionBay\Example;

use MissionBay\Api\IMcpAgent;
use MissionBay\Context\AgentContext;

class TimeNowAgent implements IMcpAgent {

	private string $id = '';
	private ?AgentContext $context = null;

	public static function getName(): string {
		return 'timenowagent';
	}

	public function getId(): string {
		return $this->id;
	}

	public function setId(string $id): void {
		$this->id = $id;
	}

	public function setContext(AgentContext $context): void {
		$this->context = $context;
	}

	public function getContext(): ?AgentContext {
		return $this->context;
	}

	public function run(array $inputs = []): array {
		return [
			'time' => date('c')
		];
	}

	public function getFunctionName(): string {
		return 'timenow';
	}

	public function getDescription(): string {
		return 'Returns the current server time in ISO 8601 format.';
	}

	public function getInputSpec(): array {
		return []; // No input required
	}

	public function getOutputSpec(): array {
		return [
			'time' => [
				'type' => 'string',
				'description' => 'Current time (ISO 8601)'
			]
		];
	}

	public function getDefaultConfig(): array {
		return [];
	}

	public function getCategory(): string {
		return 'Utility';
	}

	public function supportsAsync(): bool {
		return false;
	}

	public function getDependencies(): array {
		return [];
	}

	public function getVersion(): string {
		return '1.0.0';
	}

	public function getTags(): array {
		return ['time', 'datetime', 'now'];
	}
}

