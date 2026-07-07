<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 **********************************************************************/

namespace MissionBay\Mcp;

use Base3\Logger\Api\ILogger;
use MissionBay\Api\IAgentContext;
use MissionBay\Api\IAgentPromptProvider;

/**
 * McpPromptCatalog
 *
 * Aggregates agent prompts from materialized tools and maps them to MCP prompt
 * list/get responses.
 */
class McpPromptCatalog {

	private const LOG_SCOPE = 'missionbay_mcp';
	private const PAGE_SIZE = 50;

	/**
	 * @param IAgentPromptProvider[] $providers
	 */
	public function __construct(
		private readonly array $providers,
		private readonly IAgentContext $context,
		private readonly ILogger $logger
	) {}

	public static function getName(): string {
		return 'mcppromptcatalog';
	}

	/**
	 * @return array<string,mixed>
	 */
	public function listPrompts(?string $cursor = null): array {
		$prompts = $this->collectPrompts();
		$offset = $this->decodeCursor($cursor);
		$page = array_slice($prompts, $offset, self::PAGE_SIZE);
		$result = [
			'prompts' => $page
		];

		$nextOffset = $offset + self::PAGE_SIZE;

		if($nextOffset < count($prompts)) {
			$result['nextCursor'] = (string)$nextOffset;
		}

		return $result;
	}

	/**
	 * @param array<string,mixed> $arguments
	 * @return array<string,mixed>
	 */
	public function getPrompt(string $name, array $arguments): array {
		$name = trim($name);

		if($name === '') {
			throw new \InvalidArgumentException('Missing prompts/get parameter: name.');
		}

		foreach($this->providers as $provider) {
			if(!$provider instanceof IAgentPromptProvider) {
				continue;
			}

			$result = $provider->getPrompt($name, $arguments, $this->context);

			if(is_array($result)) {
				return $this->normalizePromptResult($result, $name);
			}
		}

		throw new \InvalidArgumentException('Unknown MCP prompt: ' . $name);
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	private function collectPrompts(): array {
		$prompts = [];
		$seen = [];

		foreach($this->providers as $provider) {
			if(!$provider instanceof IAgentPromptProvider) {
				continue;
			}

			try {
				foreach($provider->getPromptDefinitions($this->context) as $prompt) {
					if(!is_array($prompt)) {
						continue;
					}

					$prompt = $this->normalizePromptDefinition($prompt);
					$name = (string)($prompt['name'] ?? '');

					if($name === '' || isset($seen[$name])) {
						continue;
					}

					$seen[$name] = true;
					$prompts[] = $prompt;
				}
			}
			catch(\Throwable $e) {
				$this->logger->logLevel(ILogger::WARNING, 'MCP prompt provider failed while listing prompts.', [
					'scope' => self::LOG_SCOPE,
					'provider' => $provider::getName(),
					'error' => $e->getMessage()
				]);
			}
		}

		return $prompts;
	}

	/**
	 * @param array<string,mixed> $prompt
	 * @return array<string,mixed>
	 */
	private function normalizePromptDefinition(array $prompt): array {
		$name = trim((string)($prompt['name'] ?? ''));
		$title = trim((string)($prompt['title'] ?? ''));
		$description = trim((string)($prompt['description'] ?? ''));
		$arguments = $prompt['arguments'] ?? [];

		$result = [
			'name' => $name
		];

		if($title !== '') {
			$result['title'] = $title;
		}

		if($description !== '') {
			$result['description'] = $description;
		}

		if(is_array($arguments)) {
			$normalizedArguments = $this->normalizeArguments($arguments);

			if($normalizedArguments !== []) {
				$result['arguments'] = $normalizedArguments;
			}
		}

		return $result;
	}

	/**
	 * @param array<mixed> $arguments
	 * @return array<int,array<string,mixed>>
	 */
	private function normalizeArguments(array $arguments): array {
		$result = [];

		foreach($arguments as $argument) {
			if(!is_array($argument)) {
				continue;
			}

			$name = trim((string)($argument['name'] ?? ''));

			if($name === '') {
				continue;
			}

			$item = [
				'name' => $name
			];

			$description = trim((string)($argument['description'] ?? ''));

			if($description !== '') {
				$item['description'] = $description;
			}

			if(array_key_exists('required', $argument)) {
				$item['required'] = $this->toBool($argument['required']);
			}

			$result[] = $item;
		}

		return $result;
	}

	/**
	 * @param array<string,mixed> $result
	 * @return array<string,mixed>
	 */
	private function normalizePromptResult(array $result, string $name): array {
		$messages = $result['messages'] ?? [];

		if(!is_array($messages)) {
			$messages = [];
		}

		$out = [];

		foreach($messages as $message) {
			if(!is_array($message)) {
				continue;
			}

			$role = trim((string)($message['role'] ?? 'user'));
			$content = $message['content'] ?? [];

			if(!is_array($content)) {
				$content = [
					'type' => 'text',
					'text' => (string)$content
				];
			}

			$type = trim((string)($content['type'] ?? 'text'));
			$text = (string)($content['text'] ?? '');

			if($text === '') {
				continue;
			}

			$out[] = [
				'role' => $role !== '' ? $role : 'user',
				'content' => [
					'type' => $type !== '' ? $type : 'text',
					'text' => $text
				]
			];
		}

		if($out === []) {
			throw new \InvalidArgumentException('MCP prompt provider returned no messages for: ' . $name);
		}

		$response = [
			'messages' => $out
		];

		$description = trim((string)($result['description'] ?? ''));

		if($description !== '') {
			$response['description'] = $description;
		}

		return $response;
	}

	private function decodeCursor(?string $cursor): int {
		if($cursor === null || trim($cursor) === '') {
			return 0;
		}

		if(!ctype_digit($cursor)) {
			throw new \InvalidArgumentException('Invalid prompts/list cursor.');
		}

		return max(0, (int)$cursor);
	}

	private function toBool(mixed $value): bool {
		if(is_bool($value)) {
			return $value;
		}

		if(is_int($value)) {
			return $value !== 0;
		}

		return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'on'], true);
	}
}
