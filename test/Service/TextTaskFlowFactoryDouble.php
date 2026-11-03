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

final class TextTaskFlowFactoryDouble implements IAgentFlowFactory {

	public function __construct(private readonly TextTaskChatModelDouble $model) {}

	public function createFromArray(string $type, array $data, IAgentContext $context): IAgentFlow {
		$definitions = is_array($data['resources'] ?? null) ? $data['resources'] : [];
		if (count($definitions) !== 1 || (string)($definitions[0]['id'] ?? '') !== 'model') {
			throw new \RuntimeException('Text task materialized resources outside the model graph.');
		}
		$this->model->setId('model');
		$this->model->setConfig(is_array($definitions[0]['config'] ?? null) ? $definitions[0]['config'] : []);
		$this->model->init([], $context);

		return new TextTaskFlowDouble(['model' => $this->model], $context);
	}

	public function createEmpty(string $type, ?IAgentContext $context = null): IAgentFlow {
		return new TextTaskFlowDouble([], $context);
	}
}
