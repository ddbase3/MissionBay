<?php declare(strict_types=1);

namespace MissionBay\Test\Capability;

use AssistantFoundation\Api\IAiChatModel;
use AssistantFoundation\Dto\AgentCapability;
use AssistantFoundation\Dto\AgentCapabilityCatalog;
use AssistantFoundation\Dto\AgentCapabilitySelectionConfig;
use AssistantFoundation\Dto\AgentCapabilitySelectionRequest;
use AssistantFoundation\Dto\AiChatResult;
use AssistantFoundation\Dto\AiResultMetadata;
use MissionBay\Capability\HybridAgentCapabilitySelector;
use MissionBay\Capability\ProfileAwareAgentCapabilitySelector;
use MissionBay\Capability\SemanticAgentCapabilitySelector;
use PHPUnit\Framework\TestCase;

final class AgentCapabilitySelectionTestDouble2 implements IAiChatModel {
			private array $options = [];

			public function __construct(private readonly string $content) {}

			public function complete(array $messages, array $tools = []): AiChatResult {
				return new AiChatResult(
					$this->content,
					[],
					new AiResultMetadata('capability_selection', 'test', 'router')
				);
			}

			public function chat(array $messages): string { return $this->content; }
			public function raw(array $messages, array $tools = []): mixed { return $this->content; }
			public function streamResult(array $messages, array $tools, callable $onData, ?callable $onMeta = null): AiChatResult {
				$onData($this->content);
				return $this->complete($messages, $tools);
			}
			public function stream(array $messages, array $tools, callable $onData, ?callable $onMeta = null): void { $onData($this->content); }
			public function setOptions(array $options): void { $this->options = $options; }
			public function getOptions(): array { return $this->options; }
		}
