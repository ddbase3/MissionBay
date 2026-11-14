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

namespace Base3\MissionBay\Test\Resource;

use Base3\AssistantFoundation\Api\IAgentContext;
use Base3\AssistantFoundation\Api\IAiChatModel;
use Base3\CoreFoundation\State\Api\IStateStore;
use Base3\MissionBay\Resource\ConfiguredChatModelAgentResource;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Base3\ResourceFoundation\Api\IManagedFileStorageService;

final class ConfiguredChatModelAgentFileOwnerTest extends TestCase {

	/** @param array<string,mixed> $contextData */
	private function runFileOwnerCheck(array $contextData): array {
		$checked = [];
		$context = $this->createMock(IAgentContext::class);
		$context->method('getVar')->willReturnCallback(static fn(string $name) => $contextData[$name] ?? null);
		$storage = $this->createMock(IManagedFileStorageService::class);
		$storage->method('has')->willReturnCallback(static function(string $group, string $name, string $mode) use (&$checked): bool {
			$checked[] = [$group, $name, $mode];
			return false;
		});
		$storage->expects(self::never())->method('openOrCreate');
		$model = $this->createMock(IAiChatModel::class);
		$resource = (new ReflectionClass(ConfiguredChatModelAgentResource::class))->newInstanceWithoutConstructor();
		(new \ReflectionProperty(ConfiguredChatModelAgentResource::class, 'managedFileStorage'))->setValue($resource, $storage);
		$resource->init([], $context);
		$method = new \ReflectionMethod(ConfiguredChatModelAgentResource::class, 'prepareFileReferences');
		$method->invoke($resource, $model);
		return $checked;
	}

	public function testAgentExecutionUsesExactAgentOwnerOverChatbotContext(): void {
		self::assertSame([['agent', 'scheduled-uuid', IManagedFileStorageService::MODE_COLLECTION]], $this->runFileOwnerCheck([
			'agent_id' => 'scheduled-uuid',
			'agent_config_group' => 'agent',
			'chatbot_config_group' => 'uihk-chatbot',
			'chatbot_config_name' => 'default'
		]));
	}

	public function testUnknownAgentOwnerGroupNeverFallsBackToChatbotResources(): void {
		self::assertSame([], $this->runFileOwnerCheck([
			'agent_id' => 'agent-a',
			'agent_config_group' => 'other',
			'chatbot_config_group' => 'uihk-chatbot',
			'chatbot_config_name' => 'default'
		]));
	}

	public function testMissingAgentConfigGroupDoesNotExposeChatbotOrAgentFiles(): void {
		self::assertSame([], $this->runFileOwnerCheck([
			'agent_id' => 'agent-a',
			'chatbot_config_group' => 'uihk-chatbot',
			'chatbot_config_name' => 'default'
		]));
	}

	public function testChatbotExecutionRetainsItsOriginalOwnerLookup(): void {
		self::assertSame([['uihk-chatbot', 'default', IManagedFileStorageService::MODE_COLLECTION]], $this->runFileOwnerCheck([
			'chatbot_config_group' => 'uihk-chatbot',
			'chatbot_config_name' => 'default'
		]));
	}
}
