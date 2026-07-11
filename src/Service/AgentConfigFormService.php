<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 *
 * MissionBay extends the BASE3 framework with a modular runtime
 * foundation for agent flows, reusable nodes, and dockable resources.
 * It provides declarative execution for AI-driven workflows.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * https://base3.de/v/missionbay
 * https://github.com/ddbase3/MissionBay
 **********************************************************************/

namespace MissionBay\Service;

use Base3\Api\IClassMap;
use Base3\Api\IComponent;
use Base3\Api\IComponentResolver;
use Base3\Api\IMvcView;
use Base3\Api\IRequest;
use Base3\Settings\Api\ISettingsStore;
use JsonException;
use MissionBay\Api\IAgentConfigFormService;
use AssistantFoundation\Api\IAgentCapabilityProvider;
use AssistantFoundation\Api\IAgentModule;
use AssistantFoundation\Dto\AgentCapabilitySelectionConfig;
use AssistantFoundation\Dto\AgentCapabilitySourceConfig;
use AssistantFoundation\Api\IAgentMemory;
use MissionBay\Api\IAgentPromptProvider;
use MissionBay\Api\IAgentResource;
use MissionBay\Api\IAgentResourceProvider;
use MissionBay\Api\IAgentTool;
use MissionBay\Orchestrator\Profile\AgentOrchestratorProfileRepository;
use MissionBay\Profile\AgentToolProfileResolver;
use Throwable;

class AgentConfigFormService implements IAgentConfigFormService {

	protected const LLM_SETTINGS_GROUP = 'service-llm';
	protected const AGENT_COMPONENT_PRESET_GROUP = 'agent-component-preset';
	protected const CHAT_LLM_RESOURCE_ID = 'chatllm';
	protected const CHAT_LLM_RESOURCE_TYPE = 'configuredchatmodelagentresource';

	/**
	 * @var array<string,array<int,string>>|null
	 */
	protected ?array $resourceCapabilitiesByType = null;

	public function __construct(
		private readonly IRequest $request,
		private readonly ISettingsStore $settingsStore,
		private readonly IClassMap $classMap,
		private readonly IComponentResolver $componentResolver,
		private readonly AgentOrchestratorProfileRepository $orchestratorProfileRepository,
		private readonly AgentToolProfileResolver $toolProfileResolver
	) {}

	// ---------------------------------------------------------------------
	// Defaults and request handling
	// ---------------------------------------------------------------------

	public function getDefaultSettings(): array {
		return [
			'llm' => '',
			'orchestrator_profile' => AgentOrchestratorProfileRepository::DEFAULT_PROFILE_ID,
			'tool_profiles' => [],
			'expert_overrides_enabled' => false,
			'system_prompt' => '',
			'agent_flow' => [],
			'agent_components' => [],
			'capability_sources' => (new AgentCapabilitySourceConfig())->toArray(),
			'capability_selection' => (new AgentCapabilitySelectionConfig())->toArray()
		];
	}

	public function getPostedSettings(array &$errors): array {
		$agentFlow = $this->decodeConfigJsonInput(
			$this->getPostedJsonText('agent_flow_b64', 'agent_flow', 'AgentFlow configuration', $errors),
			'AgentFlow configuration',
			$errors
		);

		$agentComponentsInput = $this->decodePostedJsonValue(
			'agent_components_json_b64',
			'agent_components_json',
			'Agent components',
			$errors
		);

		if ($agentComponentsInput === null) {
			$agentComponentsInput = $this->request->request('agent_components', []);
		}

		$agentComponents = $this->normalizeAgentComponentsInput(
			$agentComponentsInput,
			$errors
		);

		$llm = $this->normalizeTechnicalKey((string)$this->request->request('llm'));

		if ($llm !== '' && !$this->llmExists($llm)) {
			$errors[] = 'Selected LLM does not exist in settings group "' . self::LLM_SETTINGS_GROUP . '": ' . $llm;
		}

		$orchestratorProfile = $this->normalizeTechnicalKey((string)$this->request->request(
			'orchestrator_profile',
			AgentOrchestratorProfileRepository::DEFAULT_PROFILE_ID
		));
		try {
			$this->orchestratorProfileRepository->getProfile($orchestratorProfile);
		}
		catch (\Throwable $e) {
			$errors[] = 'Selected orchestrator profile is not available: ' . $orchestratorProfile . ' (' . $e->getMessage() . ')';
		}

		$toolProfiles = $this->normalizeTechnicalKeyList($this->request->request('tool_profiles', []));
		$availableToolProfiles = [];
		foreach ($this->toolProfileResolver->getOptions() as $option) {
			$availableToolProfiles[(string)($option['id'] ?? '')] = true;
		}
		foreach ($toolProfiles as $toolProfile) {
			if (!isset($availableToolProfiles[$toolProfile])) {
				$errors[] = 'Selected tool profile is not available for internal agents: ' . $toolProfile;
			}
		}

		$agentFlow = $this->normalizePromptInputConnections($agentFlow);

		if ($errors === [] && $llm !== '') {
			$agentFlow = $this->applyLlmToAgentFlow($agentFlow, $llm);
		}

		return $this->normalizeSettings([
			'llm' => $llm,
			'orchestrator_profile' => $orchestratorProfile,
			'tool_profiles' => $toolProfiles,
			'expert_overrides_enabled' => $this->toBool($this->request->request('expert_overrides_enabled', false)),
			'system_prompt' => $this->normalizeTextBlock((string)$this->request->request('system_prompt')),
			'agent_flow' => $agentFlow,
			'agent_components' => $agentComponents,
			'capability_sources' => $this->normalizeCapabilitySources($this->request->request('capability_sources', [])),
			'capability_selection' => $this->normalizeCapabilitySelection($this->request->request('capability_selection', []), $errors)
		]);
	}

	public function getPostedViewValues(): array {
		$errors = [];
		$agentComponentsInput = $this->decodePostedJsonValue(
			'agent_components_json_b64',
			'agent_components_json',
			'Agent components',
			$errors
		);

		if ($agentComponentsInput === null) {
			$agentComponentsInput = $this->request->request('agent_components', []);
		}

		return [
			'llm' => $this->normalizeTechnicalKey((string)$this->request->request('llm')),
			'orchestrator_profile' => $this->normalizeTechnicalKey((string)$this->request->request('orchestrator_profile', AgentOrchestratorProfileRepository::DEFAULT_PROFILE_ID)),
			'tool_profiles' => $this->normalizeTechnicalKeyList($this->request->request('tool_profiles', [])),
			'expert_overrides_enabled' => $this->toBool($this->request->request('expert_overrides_enabled', false)),
			'system_prompt' => $this->normalizeTextBlock((string)$this->request->request('system_prompt')),
			'agent_flow_json' => $this->getPostedJsonText('agent_flow_b64', 'agent_flow', 'AgentFlow configuration', $errors),
			'agent_components' => $this->normalizeAgentComponentsViewInput($agentComponentsInput),
			'capability_sources' => $this->normalizeCapabilitySources($this->request->request('capability_sources', [])),
			'capability_selection' => $this->normalizeCapabilitySelection($this->request->request('capability_selection', []), $errors)
		];
	}

	public function normalizeSettings(array $settings): array {
		$defaults = $this->getDefaultSettings();

		$agentFlow = is_array($settings['agent_flow'] ?? null) ? $settings['agent_flow'] : $defaults['agent_flow'];
		$agentFlow = $this->normalizePromptInputConnections($agentFlow);
		$agentComponents = $this->normalizeAgentComponentsViewInput($settings['agent_components'] ?? $defaults['agent_components']);
		$llm = $this->normalizeTechnicalKey((string)($settings['llm'] ?? ''));

		if ($llm === '') {
			$llm = $this->extractLlmFromAgentFlow($agentFlow);
		}

		return [
			'llm' => $llm,
			'orchestrator_profile' => $this->normalizeTechnicalKey((string)($settings['orchestrator_profile'] ?? $defaults['orchestrator_profile'])) ?: AgentOrchestratorProfileRepository::DEFAULT_PROFILE_ID,
			'tool_profiles' => $this->normalizeTechnicalKeyList($settings['tool_profiles'] ?? $defaults['tool_profiles']),
			'expert_overrides_enabled' => $this->toBool($settings['expert_overrides_enabled'] ?? $defaults['expert_overrides_enabled']),
			'system_prompt' => $this->normalizeTextBlock((string)($settings['system_prompt'] ?? $defaults['system_prompt'])),
			'agent_flow' => $agentFlow,
			'agent_components' => $agentComponents,
			'capability_sources' => $this->normalizeCapabilitySources($settings['capability_sources'] ?? $defaults['capability_sources']),
			'capability_selection' => $this->normalizeCapabilitySelection($settings['capability_selection'] ?? $defaults['capability_selection'])
		];
	}

	public function settingsToViewValues(array $settings): array {
		$settings = $this->normalizeSettings($settings);

		return [
			'llm' => $settings['llm'],
			'orchestrator_profile' => $settings['orchestrator_profile'],
			'tool_profiles' => $settings['tool_profiles'],
			'expert_overrides_enabled' => $settings['expert_overrides_enabled'],
			'system_prompt' => $settings['system_prompt'],
			'agent_flow_json' => $this->formatConfigJson($settings['agent_flow'], '{}'),
			'agent_components' => $settings['agent_components'],
			'capability_sources' => $settings['capability_sources'],
			'capability_selection' => $settings['capability_selection']
		];
	}

	public function assignViewData(IMvcView $view, array $values, array $options = []): void {
		$formId = trim((string)($options['form_id'] ?? 'base3_agent_config'));

		if ($formId === '') {
			$formId = 'base3_agent_config';
		}

		$view->assign('agent_config_template', DIR_PLUGIN . 'MissionBay/tpl/Content/AgentConfigFormSection.php');
		$view->assign('agent_config_form', [
			'form_id' => $formId,
			'values' => $values,
			'llm_options' => $this->listLlmOptions(),
			'orchestrator_profile_options' => $this->orchestratorProfileRepository->getOptions(),
			'tool_profile_options' => $this->toolProfileResolver->getOptions(),
			'agent_component_presets' => $this->listAgentComponentPresetOptions(),
			'capability_component_options' => $this->listCapabilityComponentOptions()
		]);
	}


	/** @return array<int,string> */
	protected function normalizeTechnicalKeyList(mixed $value): array {
		if (is_string($value)) {
			$value = preg_split('/[\r\n,]+/', $value) ?: [];
		}
		if (!is_array($value)) {
			return [];
		}
		$result = [];
		foreach ($value as $item) {
			$item = $this->normalizeTechnicalKey((string)$item);
			if ($item !== '') {
				$result[$item] = $item;
			}
		}
		return array_values($result);
	}


	/** @return array<string,mixed> */
	protected function normalizeCapabilitySources(mixed $value): array {
		if (is_string($value)) {
			$decoded = json_decode($value, true);
			$value = is_array($decoded) ? $decoded : [];
		}

		return AgentCapabilitySourceConfig::fromArray(is_array($value) ? $value : [])->toArray();
	}

	/**
	 * @param array<int,string> $errors
	 * @return array<string,mixed>
	 */
	protected function normalizeCapabilitySelection(mixed $value, array &$errors = []): array {
		if (is_string($value)) {
			$decoded = json_decode($value, true);
			$value = is_array($decoded) ? $decoded : [];
		}

		$value = is_array($value) ? $value : [];
		foreach ([
			'include_tools',
			'exclude_tools',
			'include_tags',
			'exclude_tags',
			'include_categories',
			'exclude_categories',
			'always_available'
		] as $listKey) {
			if (is_string($value[$listKey] ?? null)) {
				$value[$listKey] = preg_split('/[\r\n,]+/', (string)$value[$listKey]) ?: [];
			}
		}

		try {
			return AgentCapabilitySelectionConfig::fromArray($value)->toArray();
		} catch (\Throwable $e) {
			$errors[] = 'Invalid capability selection configuration: ' . $e->getMessage();
			return (new AgentCapabilitySelectionConfig())->toArray();
		}
	}

	/** @return array<string,array<int,array<string,mixed>>> */
	protected function listCapabilityComponentOptions(): array {
		return [
			'tools' => $this->listConfiguredComponentOptions(IAgentTool::class),
			'providers' => $this->listConfiguredComponentOptions(IAgentCapabilityProvider::class),
			'modules' => $this->listConfiguredComponentOptions(IAgentModule::class),
			'resourceProviders' => $this->listConfiguredComponentOptions(IAgentResourceProvider::class),
			'promptProviders' => $this->listConfiguredComponentOptions(IAgentPromptProvider::class)
		];
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	protected function listConfiguredComponentOptions(string $interfaceName): array {
		$rows = [];

		try {
			$components = $this->componentResolver->all($interfaceName);

			foreach ($components as $component) {
				if (!$component instanceof IComponent) {
					continue;
				}

				$id = trim($component->id());
				if ($id === '') {
					continue;
				}

				$label = $id;
				$description = '';

				try {
					if ($component instanceof IAgentModule) {
						$manifest = $component->manifest();
						$label = trim($manifest->getTitle()) !== '' ? trim($manifest->getTitle()) : $manifest->getName();
						$description = trim($manifest->getDescription());
					} elseif ($component instanceof IAgentTool) {
						$definitions = $component->getToolDefinitions();
						$definition = is_array($definitions[0] ?? null) ? $definitions[0] : [];
						$function = is_array($definition['function'] ?? null) ? $definition['function'] : $definition;
						$toolLabel = trim((string)($definition['label'] ?? ($function['name'] ?? '')));
						if ($toolLabel !== '') {
							$label = $toolLabel;
						}
						$description = trim((string)($function['description'] ?? ($definition['description'] ?? '')));
					} elseif (method_exists($component, 'name')) {
						$name = trim((string)$component->name());
						if ($name !== '') {
							$label = $name;
						}
					}

					if ($description === '' && method_exists($component, 'getDescription')) {
						$description = trim((string)$component->getDescription());
					}
				} catch (\Throwable) {
					// Keep the component selectable even when optional display metadata fails.
				}

				$rows[] = [
					'id' => $id,
					'label' => $label,
					'description' => $description,
					'implementation' => $component::getName(),
					'class' => $component::class
				];
			}
		} catch (\Throwable) {
			// A failing optional component list must not make the agent editor unusable.
		}

		usort($rows, static function(array $a, array $b): int {
			$cmp = strcasecmp((string)$a['label'], (string)$b['label']);
			return $cmp !== 0 ? $cmp : strcasecmp((string)$a['id'], (string)$b['id']);
		});

		return $rows;
	}

	// ---------------------------------------------------------------------
	// Agent component options
	// ---------------------------------------------------------------------

	/**
	 * @return array<int,array<string,mixed>>
	 */
	protected function listAgentComponentPresetOptions(): array {
		$rows = [];

		try {
			$group = $this->settingsStore->getGroup(self::AGENT_COMPONENT_PRESET_GROUP);
		}
		catch (Throwable) {
			return [];
		}

		if (!is_array($group)) {
			return [];
		}

		foreach ($group as $id => $settings) {
			if (!is_string($id) || $id === '' || !is_array($settings)) {
				continue;
			}

			$rows[] = $this->normalizeAgentComponentPresetOption($id, $settings);
		}

		usort($rows, static function(array $a, array $b): int {
			$aSort = trim((string)($a['label'] ?? ''));
			$bSort = trim((string)($b['label'] ?? ''));

			if ($aSort === '') {
				$aSort = (string)($a['id'] ?? '');
			}

			if ($bSort === '') {
				$bSort = (string)($b['id'] ?? '');
			}

			$cmp = strcasecmp($aSort, $bSort);

			if ($cmp !== 0) {
				return $cmp;
			}

			return strcasecmp((string)($a['id'] ?? ''), (string)($b['id'] ?? ''));
		});

		return $rows;
	}

	/**
	 * @return array<string,mixed>
	 */
	protected function normalizeAgentComponentPresetOption(string $id, array $settings): array {
		$label = trim((string)($settings['label'] ?? ($settings['name'] ?? '')));

		if ($label === '') {
			$label = $id;
		}

		$storedCapabilities = $settings['capabilities'] ?? [];

		if (!is_array($storedCapabilities)) {
			$storedCapabilities = [];
		}

		$capabilities = $this->derivePresetCapabilities($settings, $storedCapabilities);
		$meta = $settings['meta'] ?? [];

		if (!is_array($meta)) {
			$meta = [];
		}

		return [
			'id' => $id,
			'label' => $label,
			'type' => trim((string)($settings['type'] ?? '')),
			'enabled' => $this->toBool($settings['enabled'] ?? true),
			'capabilities' => array_values(array_filter(array_map('strval', $capabilities))),
			'capability_text' => implode(', ', array_values(array_filter(array_map('strval', $capabilities)))),
			'description' => trim((string)($meta['description'] ?? ($settings['description'] ?? ''))),
			'category' => trim((string)($meta['category'] ?? '')),
			'risk' => trim((string)($meta['risk'] ?? '')),
			'status' => trim((string)($meta['status'] ?? ''))
		];
	}

	protected function normalizeAgentComponentsViewInput(mixed $value): array {
		$errors = [];

		return $this->normalizeAgentComponentsInput($value, $errors);
	}

	protected function normalizeAgentComponentsInput(mixed $value, array &$errors): array {
		if (!is_array($value)) {
			return [];
		}

		$components = [];

		foreach ($value as $row) {
			if (!is_array($row)) {
				continue;
			}

			$preset = $this->normalizeTechnicalKey((string)($row['preset'] ?? ''));

			if ($preset === '') {
				continue;
			}

			$enabled = $this->toBool($row['enabled'] ?? true);
			$presetSettings = $this->loadAgentComponentPresetSettings($preset);
			$attachAs = $this->normalizeAgentComponentAttachAs($row, $presetSettings);

			if ($attachAs === []) {
				$errors[] = 'Agent component preset "' . $preset . '" does not expose a memory or tool capability.';
				continue;
			}

			$component = [
				'preset' => $preset,
				'attach_as' => $attachAs,
				'enabled' => $enabled
			];

			$order = trim((string)($row['order'] ?? ''));

			if ($order !== '') {
				$component['order'] = (int)$order;
			}

			if (in_array('memory', $attachAs, true)) {
				$component['memory_config'] = $this->buildAgentComponentMemoryConfig($row, $order);
			}

			if (in_array('tool', $attachAs, true)) {
				$component['tool_config'] = $this->buildAgentComponentToolConfig($row);
			}

			$components[] = $component;
		}

		return $components;
	}

	protected function normalizeAgentComponentAttachAs(array $row, ?array $presetSettings = null): array {
		if ($presetSettings !== null) {
			return $this->derivePresetCapabilities($presetSettings, []);
		}

		$attachAs = [];

		if (is_array($row['attach_as'] ?? null)) {
			foreach ($row['attach_as'] as $value) {
				$value = strtolower(trim((string)$value));

				if (in_array($value, ['memory', 'tool'], true)) {
					$attachAs[] = $value;
				}
			}
		}

		if ($this->toBool($row['attach_memory'] ?? false)) {
			$attachAs[] = 'memory';
		}

		if ($this->toBool($row['attach_tool'] ?? false)) {
			$attachAs[] = 'tool';
		}

		return array_values(array_unique($attachAs));
	}

	protected function loadAgentComponentPresetSettings(string $preset): ?array {
		if ($preset === '') {
			return null;
		}

		try {
			$settings = $this->settingsStore->get(self::AGENT_COMPONENT_PRESET_GROUP, $preset, []);
		}
		catch (Throwable) {
			return null;
		}

		return is_array($settings) && $settings !== [] ? $settings : null;
	}

	/**
	 * @param array<string,mixed> $preset
	 * @param array<int|string,mixed> $fallback
	 * @return array<int,string>
	 */
	protected function derivePresetCapabilities(array $preset, array $fallback = []): array {
		$type = $this->normalizeTechnicalKey((string)($preset['type'] ?? ''));
		$map = $this->getResourceCapabilitiesByType();
		$capabilities = $map[$type] ?? [];

		if ($capabilities === []) {
			$capabilities = $fallback;
		}

		return $this->normalizeAttachCapabilityList($capabilities);
	}

	/**
	 * @return array<string,array<int,string>>
	 */
	protected function getResourceCapabilitiesByType(): array {
		if ($this->resourceCapabilitiesByType !== null) {
			return $this->resourceCapabilitiesByType;
		}

		$map = [];

		try {
			$resources = $this->classMap->getInstancesByInterface(IAgentResource::class);
		}
		catch (Throwable) {
			$this->resourceCapabilitiesByType = [];

			return [];
		}

		foreach ($resources as $resource) {
			if (!$resource instanceof IAgentResource) {
				continue;
			}

			$type = $this->normalizeTechnicalKey((string)$resource::getName());

			if ($type === '') {
				continue;
			}

			$capabilities = [];

			if ($resource instanceof IAgentMemory) {
				$capabilities[] = 'memory';
			}

			if ($resource instanceof IAgentTool) {
				$capabilities[] = 'tool';
			}

			$map[$type] = $this->normalizeAttachCapabilityList($capabilities);
		}

		$this->resourceCapabilitiesByType = $map;

		return $map;
	}

	/**
	 * @return array<int,string>
	 */
	protected function normalizeAttachCapabilityList(mixed $value): array {
		if (is_string($value)) {
			$value = explode(',', $value);
		}

		if (!is_array($value)) {
			return [];
		}

		$result = [];

		foreach ($value as $item) {
			$item = strtolower(trim((string)$item));

			if (in_array($item, ['memory', 'tool'], true)) {
				$result[] = $item;
			}
		}

		return array_values(array_unique($result));
	}

	protected function buildAgentComponentMemoryConfig(array $row, string $order): array {
		$config = is_array($row['memory_config'] ?? null) ? $row['memory_config'] : [];
		$config['enabled'] = $this->fixedValue(true);

		if ($order !== '') {
			$config['priority'] = $this->fixedValue((int)$order);
		}

		return $config;
	}

	protected function buildAgentComponentToolConfig(array $row): array {
		$config = is_array($row['tool_config'] ?? null) ? $row['tool_config'] : [];
		$config['enabled'] = $this->fixedValue(true);

		foreach (['namespace', 'label', 'description', 'category'] as $key) {
			$value = trim((string)($row[$key] ?? $this->getFixedConfigValue($config, $key, '')));

			if ($value !== '') {
				$config[$key] = $this->fixedValue($value);
			}
			else {
				unset($config[$key]);
			}
		}

		$tags = $row['tags'] ?? $this->getFixedConfigValue($config, 'tags', []);
		$tags = $this->normalizeStringList($tags);

		if ($tags !== []) {
			$config['tags'] = $this->fixedValue($tags);
		}
		else {
			unset($config['tags']);
		}

		$priority = trim((string)($row['priority'] ?? $this->getFixedConfigValue($config, 'priority', '')));

		if ($priority !== '') {
			$config['priority'] = $this->fixedValue((int)$priority);
		}
		else {
			unset($config['priority']);
		}

		return $config;
	}

	protected function fixedValue(mixed $value): array {
		return [
			'mode' => 'fixed',
			'value' => $value
		];
	}

	protected function getFixedConfigValue(array $config, string $key, mixed $default): mixed {
		$value = $config[$key] ?? null;

		if (!is_array($value)) {
			return $default;
		}

		if ((string)($value['mode'] ?? '') !== 'fixed') {
			return $default;
		}

		return $value['value'] ?? $default;
	}

	protected function normalizeStringList(mixed $value): array {
		if ($value === null || $value === '') {
			return [];
		}

		if (is_string($value)) {
			$value = explode(',', $value);
		}

		if (!is_array($value)) {
			return [];
		}

		$result = [];

		foreach ($value as $item) {
			$item = trim((string)$item);

			if ($item === '') {
				continue;
			}

			$result[] = $item;
		}

		return array_values(array_unique($result));
	}

	// ---------------------------------------------------------------------
	// LLM options
	// ---------------------------------------------------------------------

	/**
	 * @return array<int,array<string,mixed>>
	 */
	protected function listLlmOptions(): array {
		$rows = [];

		try {
			$group = $this->settingsStore->getGroup(self::LLM_SETTINGS_GROUP);
		}
		catch (Throwable) {
			return [];
		}

		if (!is_array($group)) {
			return [];
		}

		foreach ($group as $id => $settings) {
			if (!is_string($id) || $id === '' || !is_array($settings)) {
				continue;
			}

			$rows[] = $this->normalizeLlmOption($id, $settings);
		}

		usort($rows, static function(array $a, array $b): int {
			$aSort = trim((string)($a['label'] ?? ''));
			$bSort = trim((string)($b['label'] ?? ''));

			if ($aSort === '') {
				$aSort = (string)($a['id'] ?? '');
			}

			if ($bSort === '') {
				$bSort = (string)($b['id'] ?? '');
			}

			$cmp = strcasecmp($aSort, $bSort);

			if ($cmp !== 0) {
				return $cmp;
			}

			return strcasecmp((string)($a['id'] ?? ''), (string)($b['id'] ?? ''));
		});

		return $rows;
	}

	/**
	 * @return array<string,mixed>
	 */
	protected function normalizeLlmOption(string $id, array $settings): array {
		$label = trim((string)($settings['name'] ?? ($settings['label'] ?? '')));

		if ($label === '') {
			$label = $id;
		}

		return [
			'id' => $id,
			'label' => $label,
			'model' => trim((string)($settings['model'] ?? '')),
			'driver' => trim((string)($settings['driver'] ?? '')),
			'connection' => trim((string)($settings['connection'] ?? ($settings['provider'] ?? ''))),
			'enabled' => $this->toBool($settings['enabled'] ?? true)
		];
	}

	protected function llmExists(string $id): bool {
		if ($id === '') {
			return false;
		}

		try {
			$settings = $this->settingsStore->get(self::LLM_SETTINGS_GROUP, $id, []);
		}
		catch (Throwable) {
			return false;
		}

		return is_array($settings) && $settings !== [];
	}

	// ---------------------------------------------------------------------
	// AgentFlow LLM binding
	// ---------------------------------------------------------------------

	protected function applyLlmToAgentFlow(array $agentFlow, string $llm): array {
		if ($llm === '') {
			return $agentFlow;
		}

		if (!isset($agentFlow['resources']) || !is_array($agentFlow['resources'])) {
			$agentFlow['resources'] = [];
		}

		$resources = $agentFlow['resources'];
		$resourceIndex = $this->findChatLlmResourceIndex($resources);

		$resource = [
			'id' => self::CHAT_LLM_RESOURCE_ID,
			'type' => self::CHAT_LLM_RESOURCE_TYPE,
			'config' => [
				'service' => [
					'mode' => 'fixed',
					'value' => $llm
				]
			]
		];

		if ($resourceIndex !== null && isset($resources[$resourceIndex]) && is_array($resources[$resourceIndex])) {
			$resource = array_merge($resources[$resourceIndex], $resource);
			$resource['config'] = is_array($resources[$resourceIndex]['config'] ?? null)
				? $resources[$resourceIndex]['config']
				: [];
			$resource['config']['service'] = [
				'mode' => 'fixed',
				'value' => $llm
			];
			$resource['type'] = self::CHAT_LLM_RESOURCE_TYPE;
		}

		if ($resourceIndex === null) {
			$resources[] = $resource;
		}
		else {
			$resources[$resourceIndex] = $resource;
		}

		$agentFlow['resources'] = array_values($resources);

		return $agentFlow;
	}

	protected function findChatLlmResourceIndex(array $resources): ?int {
		$fallback = null;

		foreach ($resources as $index => $resource) {
			if (!is_array($resource)) {
				continue;
			}

			if ((string)($resource['id'] ?? '') === self::CHAT_LLM_RESOURCE_ID) {
				return (int)$index;
			}

			if ($fallback === null && (string)($resource['type'] ?? '') === self::CHAT_LLM_RESOURCE_TYPE) {
				$fallback = (int)$index;
			}
		}

		return $fallback;
	}

	/**
	 * @param array<string,mixed> $agentFlow
	 * @return array<string,mixed>
	 */
	protected function normalizePromptInputConnections(array $agentFlow): array {
		if (!isset($agentFlow['connections']) || !is_array($agentFlow['connections'])) {
			return $agentFlow;
		}

		$connections = [];
		$seen = [];

		foreach ($agentFlow['connections'] as $connection) {
			if (!is_array($connection)) {
				continue;
			}

			if ((string)($connection['from'] ?? '') === '__input__' && (string)($connection['output'] ?? '') === 'user') {
				$connection['output'] = 'prompt';
			}

			$key = implode("\0", [
				(string)($connection['from'] ?? ''),
				(string)($connection['output'] ?? ''),
				(string)($connection['to'] ?? ''),
				(string)($connection['input'] ?? '')
			]);

			if (isset($seen[$key])) {
				continue;
			}

			$seen[$key] = true;
			$connections[] = $connection;
		}

		$agentFlow['connections'] = $connections;

		return $agentFlow;
	}

	protected function extractLlmFromAgentFlow(array $agentFlow): string {
		$resources = $agentFlow['resources'] ?? null;

		if (!is_array($resources)) {
			return '';
		}

		$resourceIndex = $this->findChatLlmResourceIndex($resources);

		if ($resourceIndex === null || !isset($resources[$resourceIndex]) || !is_array($resources[$resourceIndex])) {
			return '';
		}

		$resource = $resources[$resourceIndex];
		$service = $resource['config']['service'] ?? null;

		if (!is_array($service)) {
			return '';
		}

		if ((string)($service['mode'] ?? '') !== 'fixed') {
			return '';
		}

		return $this->normalizeTechnicalKey((string)($service['value'] ?? ''));
	}

	// ---------------------------------------------------------------------
	// Helpers
	// ---------------------------------------------------------------------

	protected function getPostedJsonText(string $base64Field, string $plainField, string $label, array &$errors): string {
		$base64 = trim((string)$this->request->request($base64Field, ''));

		if ($base64 === '') {
			return trim((string)$this->request->request($plainField, ''));
		}

		$decoded = base64_decode($base64, true);

		if (!is_string($decoded)) {
			$errors[] = $label . ' could not be decoded from base64.';

			return '';
		}

		return trim($decoded);
	}

	protected function decodePostedJsonValue(string $base64Field, string $plainField, string $label, array &$errors): mixed {
		$raw = $this->getPostedJsonText($base64Field, $plainField, $label, $errors);

		if ($raw === '') {
			return null;
		}

		try {
			return json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
		}
		catch (JsonException $e) {
			$errors[] = $label . ' must be valid JSON: ' . $e->getMessage();

			return null;
		}
	}

	protected function decodeConfigJsonInput(string $raw, string $label, array &$errors): array {
		if ($raw === '') {
			return [];
		}

		try {
			$decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
		}
		catch (JsonException $e) {
			$errors[] = $label . ' must be valid JSON: ' . $e->getMessage();
			return [];
		}

		if (!is_array($decoded)) {
			$errors[] = $label . ' must decode to a JSON object or array.';
			return [];
		}

		return $decoded;
	}

	protected function formatConfigJson(array $data, string $emptyJson): string {
		if ($data === []) {
			return $emptyJson;
		}

		try {
			return json_encode(
				$data,
				JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
			);
		}
		catch (JsonException) {
			return $emptyJson;
		}
	}

	protected function normalizeTextBlock(string $value): string {
		return str_replace(["\r\n", "\r"], "\n", $value);
	}

	protected function normalizeTechnicalKey(string $value): string {
		$value = strtolower(trim($value));

		return preg_replace('/[^a-z0-9._-]+/', '', $value) ?? '';
	}

	protected function toBool(mixed $value): bool {
		if (is_bool($value)) {
			return $value;
		}

		if (is_int($value)) {
			return $value === 1;
		}

		$value = strtolower(trim((string)$value));

		return in_array($value, ['1', 'true', 'yes', 'on'], true);
	}

}
