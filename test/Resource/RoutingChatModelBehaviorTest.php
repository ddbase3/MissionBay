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
use Base3\MissionBay\Api\IAgentConfigValueResolver;
use Base3\MissionBay\Resource\RoutingChatModelAgentResource;
use PHPUnit\Framework\TestCase;

final class RoutingChatModelBehaviorTest extends TestCase {

	public function testRouterWithoutTargetsRejectsExecution(): void {
		$router = $this->router();
		$variables = [];
		$router->init([], $this->context($variables));
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('no targets connected');
		$router->raw([['role' => 'user', 'content' => 'test']]);
	}

	public function testRouterFallsBackToHealthyModel(): void {
		$first = $this->createStub(IAiChatModel::class);
		$first->method('raw')->willThrowException(new \RuntimeException('rate limit 429'));
		$second = $this->model('backup');
		$router = $this->router();
		$variables = [];
		$router->init(['targets' => [$first, $second]], $this->context($variables));
		$this->assertSame('backup', $router->raw([['role' => 'user', 'content' => 'test']]));
	}

	public function testRouterPropagatesFinalProviderFailure(): void {
		$first = $this->createStub(IAiChatModel::class);
		$first->method('raw')->willThrowException(new \RuntimeException('first unavailable'));
		$second = $this->createStub(IAiChatModel::class);
		$second->method('raw')->willThrowException(new \RuntimeException('second unavailable'));
		$router = $this->router();
		$variables = [];
		$router->init(['targets' => [$first, $second]], $this->context($variables));
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('second unavailable');
		$router->raw([['role' => 'user', 'content' => 'test']]);
	}

	public function testToolCapabilityPolicyRejectsUnsupportedTargets(): void {
		$router = $this->router();
		$router->setConfig(['targets' => ['target_0' => ['tools' => false]]]);
		$variables = [];
		$router->init(['targets' => [$this->model('first')]], $this->context($variables));
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('no targets match required capability: tools');
		$router->raw([['role' => 'user', 'content' => 'test']], [['type' => 'function']]);
	}

	public function testStreamingCapabilityPolicyRejectsUnsupportedTargets(): void {
		$router = $this->router();
		$router->setConfig(['targets' => ['target_0' => ['stream' => false]]]);
		$variables = [];
		$router->init(['targets' => [$this->model('first')]], $this->context($variables));
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('no targets match required capability: stream');
		$router->stream([], [], static function(string $chunk): void {});
	}

	public function testOrphanedToolMessagesAreDiscardedBeforeRawCall(): void {
		$observed = null;
		$model = $this->createStub(IAiChatModel::class);
		$model->method('raw')->willReturnCallback(static function(array $messages, array $tools) use (&$observed): string {
			$observed = $messages;
			return 'accepted';
		});
		$router = $this->router();
		$variables = [];
		$router->init(['targets' => [$model]], $this->context($variables));
		$messages = [
			['role' => 'tool', 'tool_call_id' => 'orphan', 'content' => 'not authorized'],
			['role' => 'assistant', 'tool_calls' => [['id' => 'permitted']]],
			['role' => 'tool', 'tool_call_id' => 'permitted', 'content' => 'accepted'],
			['role' => 'tool', 'tool_call_id' => 'permitted', 'content' => 'duplicate'],
			['role' => 'user', 'content' => 'continue']
		];
		$this->assertSame('accepted', $router->raw($messages));
		$this->assertSame(array_slice($messages, 1, 2), array_slice($observed, 0, 2));
		$this->assertCount(3, $observed);
		$this->assertSame('user', $observed[2]['role']);
	}

	public function testToolMessagesArePreservedWhenToolDefinitionsAreProvided(): void {
		$observed = null;
		$model = $this->createStub(IAiChatModel::class);
		$model->method('raw')->willReturnCallback(static function(array $messages, array $tools) use (&$observed): string {
			$observed = $messages;
			return 'ok';
		});
		$router = $this->router();
		$variables = [];
		$router->init(['targets' => [$model]], $this->context($variables));
		$messages = [['role' => 'tool', 'tool_call_id' => 'orphan', 'content' => 'unpaired']];
		$this->assertSame('ok', $router->raw($messages, [['type' => 'function']]));
		$this->assertSame($messages, $observed);
	}

	public function testRoundRobinRotatesBetweenTargetsWithoutStickySelection(): void {
		$router = $this->router();
		$router->setConfig(['strategy' => 'roundrobin', 'sticky' => false]);
		$variables = [];
		$router->init(['targets' => [$this->model('first'), $this->model('second')]], $this->context($variables));
		$this->assertSame('first', $router->raw([['role' => 'user', 'content' => '1']]));
		$this->assertSame('second', $router->raw([['role' => 'user', 'content' => '2']]));
		$this->assertSame('first', $router->raw([['role' => 'user', 'content' => '3']]));
	}

	public function testStickySelectionReusesPreviouslySuccessfulTarget(): void {
		$calls = 0;
		$first = $this->createStub(IAiChatModel::class);
		$first->method('raw')->willReturnCallback(static function() use (&$calls): string {
			$calls++;
			throw new \RuntimeException('first is unavailable');
		});
		$router = $this->router();
		$variables = [];
		$router->init(['targets' => [$first, $this->model('second')]], $this->context($variables));
		$this->assertSame('second', $router->raw([]));
		$this->assertSame('second', $router->raw([]));
		$this->assertSame(1, $calls);
	}

	public function testWithoutTargetsOptionsAreEmpty(): void {
		$this->assertSame([], $this->router()->getOptions());
	}

	public function testGetOptionsReadsTheFirstAvailableTarget(): void {
		$model = $this->createStub(IAiChatModel::class);
		$model->method('getOptions')->willReturn(['temperature' => 0.4]);
		$router = $this->router();
		$variables = [];
		$router->init(['targets' => [$model]], $this->context($variables));
		$this->assertSame(['temperature' => 0.4], $router->getOptions());
	}

	public function testSetOptionsBroadcastsEvenWhenOneTargetFails(): void {
		$first = $this->createStub(IAiChatModel::class);
		$first->method('setOptions')->willThrowException(new \RuntimeException('readonly model'));
		$second = $this->createMock(IAiChatModel::class);
		$second->expects($this->once())->method('setOptions')->with(['temperature' => 0.2]);
		$router = $this->router();
		$variables = [];
		$router->init(['targets' => [$first, $second]], $this->context($variables));
		$router->setOptions(['temperature' => 0.2]);
		$this->addToAssertionCount(1);
	}

	private function router(): RoutingChatModelAgentResource {
		$resolver = $this->createStub(IAgentConfigValueResolver::class);
		$resolver->method('resolveValue')->willReturnCallback(static fn(mixed $value): mixed => $value);
		return new RoutingChatModelAgentResource($resolver, 'routing-test');
	}

	private function model(string $result): IAiChatModel {
		$model = $this->createStub(IAiChatModel::class);
		$model->method('raw')->willReturn($result);
		return $model;
	}

	private function context(array &$variables): IAgentContext {
		$context = $this->createStub(IAgentContext::class);
		$context->method('getVar')->willReturnCallback(static function(string $key) use (&$variables): mixed {
			return $variables[$key] ?? null;
		});
		$context->method('setVar')->willReturnCallback(static function(string $key, mixed $value) use (&$variables): void {
			$variables[$key] = $value;
		});
		return $context;
	}
}
