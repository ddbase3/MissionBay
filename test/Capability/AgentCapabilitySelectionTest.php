<?php declare(strict_types=1);

namespace MissionBay\Test\Capability;

use AssistantFoundation\Dto\AgentCapability;
use AssistantFoundation\Dto\AgentCapabilityCatalog;
use AssistantFoundation\Dto\AgentCapabilitySelectionConfig;
use AssistantFoundation\Dto\AgentCapabilitySelectionRequest;
use MissionBay\Capability\HybridAgentCapabilitySelector;
use PHPUnit\Framework\TestCase;

final class AgentCapabilitySelectionTest extends TestCase {

	public function testLargeCatalogIsReducedByContextAndLimit(): void {
		$capabilities = [];
		for ($index = 1; $index <= 20; $index++) {
			$capabilities[] = $this->capability(
				'tool_' . $index,
				'Generic tool ' . $index,
				['generic'],
				0
			);
		}
		$capabilities[] = $this->capability(
			'weather_forecast',
			'Read the current weather forecast for a city.',
			['weather', 'forecast'],
			5
		);

		$selection = (new HybridAgentCapabilitySelector())->select(
			new AgentCapabilityCatalog($capabilities),
			new AgentCapabilitySelectionRequest(
				iteration: 1,
				contextText: 'What will the weather forecast be in Berlin tomorrow?',
				config: new AgentCapabilitySelectionConfig(maxTools: 4, selectAllThreshold: 4)
			)
		);

		$this->assertCount(4, $selection->getCapabilities());
		$this->assertContains('weather_forecast', $selection->getToolNames());
	}

	public function testRequiredAndAlwaysAvailableToolsSurviveRanking(): void {
		$catalog = new AgentCapabilityCatalog([
			$this->capability('general_info', 'General diagnostics.', ['info'], 0),
			$this->capability('crm_write', 'Updates a CRM record.', ['crm'], -10),
			$this->capability('weather', 'Weather information.', ['weather'], 50),
			$this->capability('search', 'General search.', ['search'], 40)
		]);

		$selection = (new HybridAgentCapabilitySelector())->select(
			$catalog,
			new AgentCapabilitySelectionRequest(
				iteration: 2,
				contextText: 'Find the weather.',
				config: new AgentCapabilitySelectionConfig(
					maxTools: 2,
					selectAllThreshold: 0,
					alwaysAvailable: ['general_info']
				),
				requiredToolNames: ['crm_write']
			)
		);

		$this->assertSame(['general_info', 'crm_write'], $selection->getToolNames());
	}

	public function testAgentTagsFormAHardBoundary(): void {
		$catalog = new AgentCapabilityCatalog([
			$this->capability('crm_read', 'Reads CRM records.', ['crm', 'readonly'], 0),
			$this->capability('admin_delete', 'Deletes system data.', ['administration'], 100)
		]);

		$selection = (new HybridAgentCapabilitySelector())->select(
			$catalog,
			new AgentCapabilitySelectionRequest(
				iteration: 1,
				contextText: 'Delete a system record.',
				config: new AgentCapabilitySelectionConfig(
					maxTools: 4,
					includeTags: ['crm']
				)
			)
		);

		$this->assertSame(['crm_read'], $selection->getToolNames());
	}

	private function capability(string $name, string $description, array $tags, int $priority): AgentCapability {
		return new AgentCapability(
			name: $name,
			title: str_replace('_', ' ', $name),
			description: $description,
			category: $tags[0] ?? '',
			tags: $tags,
			priority: $priority,
			definition: [
				'type' => 'function',
				'function' => [
					'name' => $name,
					'description' => $description,
					'parameters' => [
						'type' => 'object',
						'properties' => ['query' => ['type' => 'string']]
					]
				]
			]
		);
	}
}
