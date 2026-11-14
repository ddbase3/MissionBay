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

namespace Base3\MissionBay\Test\Service;

use Base3\AssistantFoundation\Api\IAgentContext;
use Base3\AssistantFoundation\Api\IAiChatModel;
use Base3\AssistantFoundation\Dto\AgentTextTaskRequest;
use Base3\AssistantFoundation\Dto\AiChatResult;
use Base3\AssistantFoundation\Dto\AiResultMetadata;
use Base3\CoreFoundation\Settings\Api\ISettingsStore;
use Base3\MissionBay\Api\IAgentAssistantContextContributionService;
use Base3\MissionBay\Api\IAgentComponentPresetMaterializer;
use Base3\MissionBay\Api\IAgentComponentPresetRepository;
use Base3\MissionBay\Api\IAgentFlow;
use Base3\MissionBay\Api\IAgentFlowCompiler;
use Base3\MissionBay\Api\IAgentFlowFactory;
use Base3\MissionBay\Api\IAgentNode;
use Base3\MissionBay\Api\IAgentResource;
use Base3\MissionBay\Api\IAgentResourceFactory;
use Base3\MissionBay\Api\IAgentTool;
use Base3\MissionBay\Context\AgentContext;
use Base3\MissionBay\Dto\AgentComponentPresetMaterialization;
use Base3\MissionBay\Dto\AgentFlowCompilation;
use Base3\MissionBay\Profile\AgentContextProfileResolver;
use Base3\MissionBay\Profile\AgentToolProfileResolver;
use Base3\MissionBay\Service\AgentTextTaskService;
use PHPUnit\Framework\TestCase;

final class TextTaskChatModelDouble implements IAgentResource, IAiChatModel {

	private string $id = '';
	private array $config = [];
	private ?IAgentContext $context = null;
	private array $tools = [];
	private array $messages = [];

	public static function getName(): string {
		return 'texttaskchatmodeldouble';
	}

	public function getId(): string {
		return $this->id;
	}

	public function setId(string $id): void {
		$this->id = $id;
	}

	public function getDescription(): string {
		return 'Text-task model test double.';
	}

	public function getDockDefinitions(): array {
		return [];
	}

	public function getConfig(): array {
		return $this->config;
	}

	public function setConfig(array $config): void {
		$this->config = $config;
	}

	public function init(array $resources, IAgentContext $context): void {
		$this->context = $context;
	}

	public function complete(array $messages, array $tools = []): AiChatResult {
		$this->messages = $messages;
		$this->tools = $tools;
		return new AiChatResult(
			'Generated title',
			[],
			new AiResultMetadata('chat', 'test', 'test-model')
		);
	}

	public function chat(array $messages): string {
		return 'Generated title';
	}

	public function raw(array $messages, array $tools = []): mixed {
		return [];
	}

	public function streamResult(
		array $messages,
		array $tools,
		callable $onData,
		?callable $onMeta = null
	): AiChatResult {
		return $this->complete($messages, $tools);
	}

	public function stream(array $messages, array $tools, callable $onData, ?callable $onMeta = null): void {
	}

	public function setOptions(array $options): void {
	}

	public function getOptions(): array {
		return [];
	}

	public function getContext(): ?IAgentContext {
		return $this->context;
	}

	public function getTools(): array {
		return $this->tools;
	}

	public function getMessages(): array {
		return $this->messages;
	}
}
