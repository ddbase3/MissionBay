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

namespace Base3\MissionBay\Test\Capability;

use Base3\AssistantFoundation\Api\IAiChatModel;
use Base3\AssistantFoundation\Dto\AgentCapability;
use Base3\AssistantFoundation\Dto\AgentCapabilityCatalog;
use Base3\AssistantFoundation\Dto\AgentCapabilitySelectionConfig;
use Base3\AssistantFoundation\Dto\AgentCapabilitySelectionRequest;
use Base3\AssistantFoundation\Dto\AiChatResult;
use Base3\AssistantFoundation\Dto\AiResultMetadata;
use Base3\MissionBay\Capability\HybridAgentCapabilitySelector;
use Base3\MissionBay\Capability\ProfileAwareAgentCapabilitySelector;
use Base3\MissionBay\Capability\SemanticAgentCapabilitySelector;
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
