<?php declare(strict_types=1);

namespace MissionBay\Test\Resource;

use MissionBay\Api\IAgentInfoTopicProvider;
use MissionBay\Dto\AgentInfoRequest;
use MissionBay\Dto\AgentInfoResult;

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
