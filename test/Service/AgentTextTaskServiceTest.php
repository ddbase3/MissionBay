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

final class AgentTextTaskServiceTest extends TestCase {

	public function testTextTaskUsesConfiguredModelWithoutToolsOrConversationMemory(): void {
		$model = new TextTaskChatModelDouble();
		$store = new TextTaskSettingsStoreDouble();
		$repository = new TextTaskPresetRepositoryDouble();
		$factory = new TextTaskResourceFactoryDouble($model);
		$service = new AgentTextTaskService(
			new TextTaskFlowCompilerDouble(),
			new TextTaskFlowFactoryDouble($model),
			new TextTaskPresetMaterializerDouble(),
			new AgentContextProfileResolver($store, $repository, $factory),
			new AgentToolProfileResolver($store, $repository),
			new TextTaskContextContributionServiceDouble()
		);

		$result = $service->executeTextTask(new AgentTextTaskRequest(
			['agent_runtime' => 'missionbay'],
			'chat-title',
			'Create a concise title.',
			'User: Hello',
			['conversation_id' => 'must-not-leak', 'reference' => '/category/1']
		));

		$this->assertSame('Generated title', $result->getContent());
		$this->assertSame([], $model->getTools());
		$this->assertNull($model->getContext()?->getVar('conversation_id'));
		$this->assertSame('agent-text-task', $model->getContext()?->getVar('source'));
		$this->assertSame('chat-title', $model->getContext()?->getVar('agent_text_task'));
	}

	public function testToolProfileContributesCatalogWithoutExecutableTools(): void {
		$model = new TextTaskChatModelDouble();
		$store = new TextTaskSettingsStoreDouble([
			AgentToolProfileResolver::SETTINGS_GROUP => [
				'admin-tools' => [
					'enabled' => true,
					'internal_enabled' => true,
					'tools' => ['admin-tool']
				]
			]
		]);
		$repository = new TextTaskPresetRepositoryDouble([
			'admin-tool' => [
				'id' => 'admin-tool',
				'enabled' => true,
				'capabilities' => ['tool']
			]
		]);
		$factory = new TextTaskResourceFactoryDouble($model);
		$service = new AgentTextTaskService(
			new TextTaskFlowCompilerDouble(),
			new TextTaskFlowFactoryDouble($model),
			new TextTaskPresetMaterializerDouble(new TextTaskToolDouble()),
			new AgentContextProfileResolver($store, $repository, $factory),
			new AgentToolProfileResolver($store, $repository),
			new TextTaskContextContributionServiceDouble()
		);

		$service->executeTextTask(new AgentTextTaskRequest(
			['tool_profiles' => ['admin-tools']],
			'opening-message',
			'Describe available capabilities only.',
			'Create an opening message.',
			[],
			false,
			true
		));

		$this->assertSame([], $model->getTools());
		$catalogMessages = array_values(array_filter(
			$model->getMessages(),
			static fn(array $message): bool => str_contains((string)($message['content'] ?? ''), 'manage_plugins')
		));
		$this->assertCount(1, $catalogMessages);
	}
}

require_once __DIR__ . '/TextTaskFlowCompilerDouble.php';
require_once __DIR__ . '/TextTaskFlowFactoryDouble.php';
require_once __DIR__ . '/TextTaskFlowDouble.php';
require_once __DIR__ . '/TextTaskResourceFactoryDouble.php';
require_once __DIR__ . '/TextTaskPresetMaterializerDouble.php';
require_once __DIR__ . '/TextTaskChatModelDouble.php';
require_once __DIR__ . '/TextTaskToolDouble.php';
require_once __DIR__ . '/TextTaskSettingsStoreDouble.php';
require_once __DIR__ . '/TextTaskPresetRepositoryDouble.php';
require_once __DIR__ . '/TextTaskContextContributionServiceDouble.php';
