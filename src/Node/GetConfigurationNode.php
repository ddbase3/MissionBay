<?php declare(strict_types=1);

namespace MissionBay\Node;

use MissionBay\Agent\AgentContext;
use MissionBay\Api\IAgentNode;
use Base3\Configuration\Api\IConfiguration;

class GetConfigurationNode extends AbstractAgentNode {

	private IConfiguration $configuration;

	public function __construct(IConfiguration $configuration, ?string $id = null) {
		parent::__construct($id);
		$this->configuration = $configuration;
	}

	public static function getName(): string {
		return 'getconfigurationnode';
	}

	public function getInputDefinitions(): array {
		return ['section', 'key'];
	}

	public function getOutputDefinitions(): array {
		return ['value', 'error'];
	}

	public function execute(array $inputs, AgentContext $context): array {
		$section = $inputs['section'] ?? null;
		$key = $inputs['key'] ?? null;

		if (!$section || !$key) {
			return ['error' => 'Missing section or key input'];
		}

		$sectionData = $this->configuration->get($section);

		if (!is_array($sectionData)) {
			return ['error' => "Config section '$section' not found or invalid"];
		}

		if (!array_key_exists($key, $sectionData)) {
			return ['error' => "Config key '$key' not found in section '$section'"];
		}

		return ['value' => $sectionData[$key]];
	}

	public function getDescription(): string {
		return 'Retrieves a specific configuration value from a named section using Base3\'s IConfiguration interface. Useful for injecting secrets, environment settings, or other global parameters into a flow.';
	}
}

