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
