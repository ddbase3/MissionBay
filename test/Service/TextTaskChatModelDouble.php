<?php declare(strict_types=1);

namespace MissionBay\Test\Service;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAiChatModel;
use AssistantFoundation\Dto\AgentTextTaskRequest;
use AssistantFoundation\Dto\AiChatResult;
use AssistantFoundation\Dto\AiResultMetadata;
use Base3\Settings\Api\ISettingsStore;
use MissionBay\Api\IAgentAssistantContextContributionService;
use MissionBay\Api\IAgentComponentPresetMaterializer;
use MissionBay\Api\IAgentComponentPresetRepository;
use MissionBay\Api\IAgentFlow;
use MissionBay\Api\IAgentFlowCompiler;
use MissionBay\Api\IAgentFlowFactory;
use MissionBay\Api\IAgentNode;
use MissionBay\Api\IAgentResource;
use MissionBay\Api\IAgentResourceFactory;
use MissionBay\Api\IAgentTool;
use MissionBay\Context\AgentContext;
use MissionBay\Dto\AgentComponentPresetMaterialization;
use MissionBay\Dto\AgentFlowCompilation;
use MissionBay\Profile\AgentContextProfileResolver;
use MissionBay\Profile\AgentToolProfileResolver;
use MissionBay\Service\AgentTextTaskService;
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
		callable $onMeta = null
	): AiChatResult {
		return $this->complete($messages, $tools);
	}

	public function stream(array $messages, array $tools, callable $onData, callable $onMeta = null): void {
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
