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

namespace Base3\MissionBay\Test\Resource;

use Base3\MissionBay\Api\IAgentInfoTopicProvider;
use Base3\MissionBay\Dto\AgentInfoRequest;
use Base3\MissionBay\Dto\AgentInfoResult;

final class GeneralInfoTestTopicProvider implements IAgentInfoTopicProvider {

	public int $calls = 0;
	public ?AgentInfoRequest $lastRequest = null;
	public bool $throwOnHandle = false;
	public bool $supportsAll = false;
	public ?AgentInfoResult $response = null;

	public function __construct(
		public string $topic = 'library',
		public int $priority = 80,
		public array $aliases = ['books', ' reference ', 'books', ''],
		public string $title = 'Library information'
	) {}

	public static function getName(): string {
		return 'generalinfotesttopicprovider';
	}

	public function getTopic(): string {
		return $this->topic;
	}

	public function getTopicAliases(): array {
		return $this->aliases;
	}

	public function getTitle(): string {
		return $this->title;
	}

	public function getDescription(): string {
		return 'Read-only library catalogue';
	}

	public function getPriority(): int {
		return $this->priority;
	}

	public function supports(string $topic): bool {
		return $this->supportsAll;
	}

	public function handle(AgentInfoRequest $request): AgentInfoResult {
		$this->calls++;
		$this->lastRequest = $request;
		if ($this->throwOnHandle) {
			throw new \RuntimeException('private implementation details');
		}

		return $this->response ?? AgentInfoResult::createSuccess('', '', '', items: [['id' => 'book-a']]);
	}
}
