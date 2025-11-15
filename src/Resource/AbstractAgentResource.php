<?php declare(strict_types=1);

namespace MissionBay\Resource;

use MissionBay\Api\IAgentContext;
use MissionBay\Api\IAgentResource;
use MissionBay\Agent\AgentNodeDock;

/**
 * Abstract base class for agent resources.
 *
 * Provides default implementation for ID handling, configuration,
 * and optional dock definitions. Subclasses must define their name
 * and human-readable description.
 */
abstract class AbstractAgentResource implements IAgentResource {

	protected string $id;
	protected array $config = [];

	public function __construct(?string $id = null) {
		$this->id = $id ?? uniqid('resource_', true);
	}

	public function getId(): string {
		return $this->id;
	}

	public function setId(string $id): void {
		$this->id = $id;
	}

	public function getConfig(): array {
		return $this->config;
	}

	public function setConfig(array $config): void {
		$this->config = $config;
	}

	/**
	 * Default implementation returns no docks.
	 * Override in concrete resource if needed.
	 *
	 * @return AgentNodeDock[]
	 */
	public function getDockDefinitions(): array {
		return [];
	}

	/**
	 * Default initialization for docked resources and context.
	 * Concrete resources can override this to pull their dependencies.
	 *
	 * @param array<string, IAgentResource[]> $resources Docked resources by dock name
	 * @param IAgentContext $context Flow-wide context
	 */
	public function init(array $resources, IAgentContext $context): void {
		// no-op by default
	}

	/**
	 * Returns the internal technical name of the resource type.
	 * This is used to map flow JSON to PHP classes.
	 *
	 * @return string e.g. "loggerresource"
	 */
	abstract public static function getName(): string;

	/**
	 * Returns a human-readable description of the resource.
	 *
	 * @return string
	 */
	abstract public function getDescription(): string;

	/**
	 * Emits a runtime event to the active AgentContext.
	 *
	 * Resources can call this method to send non-blocking informational
	 * events during long-running operations. Events do not affect the
	 * regular node output and are handled separately by the context or
	 * the hosting system.
	 *
	 * Examples of runtime events:
	 * - status_update: progress information for the UI
	 * - canvas_content: structured output intended for a canvas panel
	 * - canvas_close: request to close an active canvas
	 *
	 * If the AgentContext does not support event emission, calling this
	 * method has no effect. This keeps the behavior fully backwards
	 * compatible and safe to use in all environments.
	 *
	 * @param IAgentContext $ctx Flow-wide context instance
	 * @param array<string,mixed> $event Structured event payload
	 * @return void
	 */
	protected function emit(IAgentContext $ctx, array $event): void {
		if (method_exists($ctx, 'emitEvent')) {
			$ctx->emitEvent($event);
		}
	}
}

