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
