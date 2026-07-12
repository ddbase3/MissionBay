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

namespace MissionBay\Resource\AgentMemory\Time;

use AssistantFoundation\Dto\AgentInstructionBlock;
use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentContextContributor;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Resource\AbstractAgentResource;

/**
 * TimeMemoryAgentResource
 *
 * Provides a single system message with the current date/time/timezone.
 * Does not store or accept appended history.
 */
class TimeMemoryAgentResource extends AbstractAgentResource implements IAgentContextContributor {

	private IAgentConfigValueResolver $resolver;
	private int $priority = 10;

	public function __construct(IAgentConfigValueResolver $resolver, ?string $id = null) {
		parent::__construct($id);
		$this->resolver = $resolver;
	}

	public static function getName(): string {
		return 'timememoryagentresource';
	}

	public function getDescription(): string {
		return 'Provides the current date/time/timezone as a system message.';
	}

	public function setConfig(array $config): void {
		parent::setConfig($config);
		$this->priority = (int)($this->resolver->resolveValue($config['priority'] ?? null) ?? 10);
	}

	// ------- Context contribution -------

	public function contribute(IAgentContext $context): iterable {
		$now = new \DateTimeImmutable('now', new \DateTimeZone(date_default_timezone_get()));

		return [new AgentInstructionBlock(
			id: 'current-time',
			content: 'Current time is ' . $now->format(\DateTimeInterface::ATOM)
				. ' (weekday: ' . $now->format('l') . ')',
			source: $this->id(),
			metadata: ['implementation' => static::getName()]
		)];
	}

	public function getPriority(): int {
		return $this->priority;
	}

}

