<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 **********************************************************************/

namespace MissionBay\Capability;

use AssistantFoundation\Api\IAgentCapabilityProvider;
use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentModule;
use AssistantFoundation\Dto\AgentCapabilitySourceConfig;
use AssistantFoundation\Dto\AgentModuleActivation;
use AssistantFoundation\Dto\AgentStageMount;
use Base3\Api\IComponent;
use Base3\Api\IComponentResolver;
use MissionBay\Api\IAgentPromptProvider;
use MissionBay\Api\IAgentResourceProvider;
use MissionBay\Api\IAgentTool;
use MissionBay\Dto\Assistant\AgentCapabilityDiscoveryResult;

/**
 * Resolves only the component ids explicitly configured for an agent and
 * activates their run-local capability contributions.
 */
final class AgentCapabilityDiscoveryService {

	public function __construct(private readonly IComponentResolver $components) {}

	/**
	 * @param array<int,IAgentTool> $baseTools
	 */
	public function discover(array $baseTools, AgentCapabilitySourceConfig $config, IAgentContext $context): AgentCapabilityDiscoveryResult {
		$tools = [];
		$resourceProviders = [];
		$promptProviders = [];
		$instructions = [];
		$stageMounts = [];
		$warnings = [];
		$errors = [];
		$resolvedToolIds = [];
		$resolvedProviderIds = [];
		$resolvedModuleIds = [];
		$resolvedResourceProviderIds = [];
		$resolvedPromptProviderIds = [];

		foreach ($baseTools as $tool) {
			if ($tool instanceof IAgentTool) {
				$this->addObject($tools, $tool);
			}
		}

		foreach ($config->getToolIds() as $id) {
			$component = $this->resolveComponent(IAgentTool::class, 'tool', $id, $config, $warnings, $errors);
			if (!$component instanceof IAgentTool) {
				continue;
			}
			$this->addObject($tools, $component);
			$resolvedToolIds[] = $id;
		}

		foreach ($config->getResourceProviderIds() as $id) {
			$component = $this->resolveComponent(IAgentResourceProvider::class, 'resource provider', $id, $config, $warnings, $errors);
			if (!$component instanceof IAgentResourceProvider) {
				continue;
			}
			$this->addObject($resourceProviders, $component);
			$resolvedResourceProviderIds[] = $id;
		}

		foreach ($config->getPromptProviderIds() as $id) {
			$component = $this->resolveComponent(IAgentPromptProvider::class, 'prompt provider', $id, $config, $warnings, $errors);
			if (!$component instanceof IAgentPromptProvider) {
				continue;
			}
			$this->addObject($promptProviders, $component);
			$resolvedPromptProviderIds[] = $id;
		}

		foreach ($config->getProviderIds() as $id) {
			$provider = $this->resolveComponent(IAgentCapabilityProvider::class, 'capability provider', $id, $config, $warnings, $errors);
			if (!$provider instanceof IAgentCapabilityProvider) {
				continue;
			}

			try {
				$this->collectTools($provider->tools($context), $tools, 'Capability provider ' . $id, $warnings);
				$this->collectResourceProviders($provider->resourceProviders($context), $resourceProviders, 'Capability provider ' . $id, $warnings);
				$this->collectPromptProviders($provider->promptProviders($context), $promptProviders, 'Capability provider ' . $id, $warnings);
				$resolvedProviderIds[] = $id;
			} catch (\Throwable $e) {
				$this->recordFailure('Capability provider "' . $id . '" failed: ' . $e->getMessage(), $config, $warnings, $errors);
			}
		}

		foreach ($config->getModuleIds() as $id) {
			$module = $this->resolveComponent(IAgentModule::class, 'module', $id, $config, $warnings, $errors);
			if (!$module instanceof IAgentModule) {
				continue;
			}

			try {
				$manifest = $module->manifest();
				$activation = $module->activate($context);
				if (!$activation instanceof AgentModuleActivation) {
					throw new \RuntimeException('Module activation did not return AgentModuleActivation.');
				}
				$this->collectTools($activation->getTools(), $tools, 'Module ' . $id, $warnings);
				$this->collectResourceProviders($activation->getResourceProviders(), $resourceProviders, 'Module ' . $id, $warnings);
				$this->collectPromptProviders($activation->getPromptProviders(), $promptProviders, 'Module ' . $id, $warnings);
				foreach ($activation->getInstructions() as $instruction) {
					$instruction = trim($instruction);
					if ($instruction !== '') {
						$instructions[$instruction] = true;
					}
				}
				foreach ($activation->getStages() as $mount) {
					if ($mount instanceof AgentStageMount) {
						$stageMounts[] = $mount;
					}
				}
				$resolvedModuleIds[] = $id;
				if ($manifest->getName() === '') {
					$warnings[] = 'Module "' . $id . '" returned an empty manifest name.';
				}
			} catch (\Throwable $e) {
				$this->recordFailure('Module "' . $id . '" failed: ' . $e->getMessage(), $config, $warnings, $errors);
			}
		}

		return new AgentCapabilityDiscoveryResult(
			sourceConfig: $config,
			tools: array_values($tools),
			resourceProviders: array_values($resourceProviders),
			promptProviders: array_values($promptProviders),
			instructions: array_keys($instructions),
			stageMounts: $stageMounts,
			resolvedToolIds: array_values(array_unique($resolvedToolIds)),
			resolvedProviderIds: array_values(array_unique($resolvedProviderIds)),
			resolvedModuleIds: array_values(array_unique($resolvedModuleIds)),
			resolvedResourceProviderIds: array_values(array_unique($resolvedResourceProviderIds)),
			resolvedPromptProviderIds: array_values(array_unique($resolvedPromptProviderIds)),
			warnings: array_values(array_unique($warnings)),
			errors: array_values(array_unique($errors))
		);
	}

	private function resolveComponent(
		string $interface,
		string $type,
		string $id,
		AgentCapabilitySourceConfig $config,
		array &$warnings,
		array &$errors
	): ?object {
		try {
			$component = $this->components->get($interface, $id);
		} catch (\Throwable $e) {
			$this->recordFailure(
				'Unable to resolve configured ' . $type . ' component "' . $id . '": ' . $e->getMessage(),
				$config,
				$warnings,
				$errors
			);
			return null;
		}

		if (!is_object($component) || !is_a($component, $interface)) {
			$this->recordFailure(
				'Configured ' . $type . ' component was not found or has the wrong interface: ' . $id,
				$config,
				$warnings,
				$errors
			);
			return null;
		}

		return $component;
	}

	private function recordFailure(string $message, AgentCapabilitySourceConfig $config, array &$warnings, array &$errors): void {
		if ($config->isStrict()) {
			$errors[] = $message;
			return;
		}
		$warnings[] = $message;
	}

	private function collectTools(iterable $values, array &$target, string $source, array &$warnings): void {
		foreach ($values as $value) {
			if (!$value instanceof IAgentTool) {
				$warnings[] = $source . ' returned a non-IAgentTool value.';
				continue;
			}
			$this->addObject($target, $value);
		}
	}

	private function collectResourceProviders(iterable $values, array &$target, string $source, array &$warnings): void {
		foreach ($values as $value) {
			if (!$value instanceof IAgentResourceProvider) {
				$warnings[] = $source . ' returned a non-IAgentResourceProvider value.';
				continue;
			}
			$this->addObject($target, $value);
		}
	}

	private function collectPromptProviders(iterable $values, array &$target, string $source, array &$warnings): void {
		foreach ($values as $value) {
			if (!$value instanceof IAgentPromptProvider) {
				$warnings[] = $source . ' returned a non-IAgentPromptProvider value.';
				continue;
			}
			$this->addObject($target, $value);
		}
	}

	private function addObject(array &$target, object $value): void {
		$key = 'object:' . spl_object_id($value);
		if ($value instanceof IComponent) {
			$id = trim($value->id());
			if ($id !== '') {
				$key = 'component:' . $id;
			}
		}
		$target[$key] = $value;
	}
}
