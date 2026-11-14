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

namespace Base3\MissionBay\Test\ChatModel;

use Base3\AssistantFoundation\Api\IAiProvider;
use Base3\CoreFoundation\Api\IClassMap;
use Base3\Core\Event\EventManager;
use Base3\MissionBay\Ai\AiProviderRequestEventDispatcher;
use Base3\MissionBay\ChatModel\MistralChatModel;
use Base3\MissionBay\ChatModel\OpenAiChatModel;
use Base3\MissionBay\ChatModel\OpenAiCompatibleChatModel;
use PHPUnit\Framework\TestCase;

final class CapturingStreamingProvider implements IAiProvider {

	/** @var array<string,mixed> */
	public array $payload = [];

	/** @var array<string,mixed> */
	private array $options = [];

	public function __construct(
		private readonly bool $returnToolCall = true
	) {}

	public static function getName(): string {
		return 'capturingstreamingprovider';
	}

	public function setOptions(array $options): void {
		$this->options = array_merge($this->options, $options);
	}

	public function getOptions(): array {
		return $this->options;
	}

	public function request(string $path, array $payload, array $options = []): array {
		$this->payload = $payload;
		return [];
	}

	public function stream(string $path, array $payload, callable $onChunk, array $options = []): void {
		$this->payload = $payload;

		if(!$this->returnToolCall) {
			$onChunk("data: {\"choices\":[{\"index\":0,\"delta\":{\"content\":\"Hello!\"},\"finish_reason\":null}]}\n\n");
			$onChunk("data: {\"choices\":[{\"index\":0,\"delta\":{},\"finish_reason\":\"stop\"}]}\n\n");
			$onChunk("data: [DONE]\n\n");
			return;
		}

		$onChunk("data: {\"choices\":[{\"index\":0,\"delta\":{\"tool_calls\":[{\"index\":0,\"id\":\"call-webdav\",\"type\":\"function\",\"function\":{\"name\":\"get_global_webdav_status\",\"arguments\":\"{}\"}}]},\"finish_reason\":null}]}\n\n");
		$onChunk("data: {\"choices\":[{\"index\":0,\"delta\":{},\"finish_reason\":\"tool_calls\"}]}\n\n");
		$onChunk("data: [DONE]\n\n");
	}
}
