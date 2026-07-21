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

namespace MissionBay\Resource\AgentContext\Text;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentContextContributor;
use AssistantFoundation\Dto\AgentInstructionBlock;
use Base3\Api\ISchemaProvider;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Resource\AbstractAgentResource;

/**
 * StaticTextContextAgentResource
 *
 * Contributes configured static text to the system context of an agent turn.
 */
final class StaticTextContextAgentResource extends AbstractAgentResource implements IAgentContextContributor, ISchemaProvider {

	private string $text = '';
	private int $priority = 30;

	public function __construct(
		private readonly IAgentConfigValueResolver $resolver,
		?string $id = null
	) {
		parent::__construct($id);
	}

	public static function getName(): string {
		return 'statictextcontextagentresource';
	}

	public function getDescription(): string {
		return 'Contributes configurable static text to the agent system context.';
	}

	/**
	 * @return array<string,mixed>
	 */
	public function getSchema(): array {
		return [
			'$schema' => 'https://json-schema.org/draft-2020-12/schema',
			'type' => 'object',
			'properties' => [
				'text' => [
					'type' => 'string',
					'description' => 'Static multiline text added to the agent system context.',
					'default' => '',
					'x-ui' => [
						'control' => 'textarea',
						'rows' => 12
					]
				],
				'priority' => [
					'type' => 'integer',
					'description' => 'Context contribution priority. Lower values are loaded first.',
					'default' => 30,
					'minimum' => 0,
					'maximum' => 1000
				]
			],
			'required' => ['text']
		];
	}

	public function setConfig(array $config): void {
		parent::setConfig($config);

		$this->text = (string)($this->resolver->resolveValue($config['text'] ?? null) ?? '');
		$this->priority = $this->normalizePriority(
			(int)($this->resolver->resolveValue($config['priority'] ?? null) ?? 30)
		);
	}

	public function contribute(IAgentContext $context): iterable {
		if (trim($this->text) === '') {
			return [];
		}

		return [new AgentInstructionBlock(
			id: 'static-text-context:' . $this->id(),
			content: $this->text,
			source: $this->id(),
			metadata: ['implementation' => static::getName()]
		)];
	}

	public function getPriority(): int {
		return $this->priority;
	}

	private function normalizePriority(int $priority): int {
		return max(0, min(1000, $priority));
	}
}
