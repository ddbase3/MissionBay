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

use Base3\AssistantFoundation\Api\IAgentConversationMemory;
use Base3\AssistantFoundation\Dto\AgentConversationScope;
use Base3\CoreFoundation\Translation\Api\ITranslation;
use Base3\CoreFoundation\Usermanager\Api\IUsermanager;
use Base3\CoreFoundation\Usermanager\User;
use Base3\MissionBay\Context\AgentContext;
use Base3\MissionBay\Resource\RoutedConversationMemoryAgentResource;
use PHPUnit\Framework\TestCase;

final class RoutedConversationMemoryAgentResourceCoverageTest extends TestCase {

	private function router(?User &$user): RoutedConversationMemoryAgentResource {
		$manager = $this->createStub(IUsermanager::class);
		$manager->method('getUser')->willReturnCallback(static function () use (&$user): ?User {
			return $user;
		});
		$translation = $this->createStub(ITranslation::class);
		$translation->method('translate')->willReturn('Translated');
		return new RoutedConversationMemoryAgentResource($manager, $translation, 'router-1');
	}

	private function connected(?User &$user, ?IAgentConversationMemory $authenticated = null, ?IAgentConversationMemory $anonymous = null): RoutedConversationMemoryAgentResource {
		$router = $this->router($user);
		$router->init([
			'authenticated' => [$authenticated ?? $this->createStub(IAgentConversationMemory::class)],
			'anonymous' => [$anonymous ?? $this->createStub(IAgentConversationMemory::class)]
		], new AgentContext());
		return $router;
	}

	public function testTechnicalNameAndIdAreStable(): void {
		$user = null;
		$router = $this->router($user);
		$this->assertSame('routedconversationmemoryagentresource', $router::getName());
		$this->assertSame('router-1', $router->getId());
		$this->assertSame('Translated', $router->getDescription());
	}

	public function testTwoDistinctBackendsAreMandatory(): void {
		$user = null;
		$router = $this->router($user);
		$memory = $this->createStub(IAgentConversationMemory::class);
		$this->expectException(\RuntimeException::class);
		$router->init(['authenticated' => [$memory], 'anonymous' => [$memory]], new AgentContext());
	}

	public function testMissingAnonymousBackendIsRejected(): void {
		$user = null;
		$router = $this->router($user);
		$this->expectException(\RuntimeException::class);
		$router->init(['authenticated' => [$this->createStub(IAgentConversationMemory::class)]], new AgentContext());
	}

	public function testMissingAuthenticatedBackendIsRejected(): void {
		$user = null;
		$router = $this->router($user);
		$this->expectException(\RuntimeException::class);
		$router->init(['anonymous' => [$this->createStub(IAgentConversationMemory::class)]], new AgentContext());
	}

	public function testBothBackendsAreRequiredInDockDefinitions(): void {
		$user = null;
		$docks = $this->router($user)->getDockDefinitions();
		$this->assertSame(['authenticated', 'anonymous'], array_map(static fn($dock): string => $dock->name, $docks));
		$this->assertTrue($docks[0]->required);
		$this->assertTrue($docks[1]->required);
	}

	public function testAnonymousConversationsReadOnlyFromAnonymousBackend(): void {
		$user = null;
		$authenticated = $this->createStub(IAgentConversationMemory::class);
		$anonymous = $this->createStub(IAgentConversationMemory::class);
		$anonymous->method('listConversations')->willReturn([]);
		$this->assertSame([], $this->connected($user, $authenticated, $anonymous)->listConversations());
	}

	public function testAuthenticatedReadsUseAuthenticatedBackend(): void {
		$user = User::fromArray(['id' => 77]);
		$authenticated = $this->createStub(IAgentConversationMemory::class);
		$anonymous = $this->createStub(IAgentConversationMemory::class);
		$authenticated->method('loadNodeHistory')->willReturn([['id' => 'auth-message']]);
		$anonymous->method('loadNodeHistory')->willReturn([['id' => 'anon-message']]);
		$this->assertSame([['id' => 'auth-message']], $this->connected($user, $authenticated, $anonymous)->loadNodeHistory('node'));
	}

	public function testChangesInIdentitySwitchBackendWithoutCachedSelection(): void {
		$user = User::fromArray(['id' => 'abc']);
		$authenticated = $this->createStub(IAgentConversationMemory::class);
		$anonymous = $this->createStub(IAgentConversationMemory::class);
		$authenticated->method('getPriority')->willReturn(13);
		$anonymous->method('getPriority')->willReturn(29);
		$router = $this->connected($user, $authenticated, $anonymous);
		$this->assertSame(13, $router->getPriority());
		$user = null;
		$this->assertSame(29, $router->getPriority());
		$user = User::fromArray(['id' => 'other']);
		$this->assertSame(13, $router->getPriority());
	}

	public function testAuthenticatedScopeIsBoundToTrustedNativeId(): void {
		$user = User::fromArray(['id' => 42]);
		$bound = null;
		$authenticated = $this->createStub(IAgentConversationMemory::class);
		$authenticated->method('bindConversationScope')->willReturnCallback(static function (AgentConversationScope $scope) use (&$bound): void {
			$bound = $scope;
		});
		$router = $this->connected($user, $authenticated);
		$scope = new AgentConversationScope(AgentConversationScope::OWNER_USER, '42', 'channel');
		$router->bindConversationScope($scope);
		$this->assertSame($scope, $bound);
	}

	public function testAnonymousScopeUsesOpaqueSessionIdentity(): void {
		$user = null;
		$bound = null;
		$anonymous = $this->createStub(IAgentConversationMemory::class);
		$anonymous->method('bindConversationScope')->willReturnCallback(static function (AgentConversationScope $scope) use (&$bound): void {
			$bound = $scope;
		});
		$router = $this->connected($user, null, $anonymous);
		$scope = new AgentConversationScope(AgentConversationScope::OWNER_SESSION, AgentConversationScope::ownerIdForSession('test-session'), 'channel');
		$router->bindConversationScope($scope);
		$this->assertSame($scope, $bound);
	}

	public function testAuthenticatedUserCannotBindSomeoneElsesScope(): void {
		$user = User::fromArray(['id' => 'user-1']);
		$router = $this->connected($user);
		$this->expectException(\RuntimeException::class);
		$router->bindConversationScope(new AgentConversationScope(AgentConversationScope::OWNER_USER, 'user-2', 'channel'));
	}

	public function testAuthenticatedUserCannotBindSessionScope(): void {
		$user = User::fromArray(['id' => 'user-1']);
		$this->expectException(\RuntimeException::class);
		$this->connected($user)->bindConversationScope(new AgentConversationScope(AgentConversationScope::OWNER_SESSION, AgentConversationScope::ownerIdForSession('session'), 'channel'));
	}

	public function testAnonymousUserCannotBindPersistentUserScope(): void {
		$user = null;
		$this->expectException(\RuntimeException::class);
		$this->connected($user)->bindConversationScope(new AgentConversationScope(AgentConversationScope::OWNER_USER, 'user-1', 'channel'));
	}

	public function testMissingConnectionFailsClosed(): void {
		$user = null;
		$this->expectException(\RuntimeException::class);
		$this->router($user)->listConversations();
	}

	public function testInvalidNativeUserIdentityIsRejected(): void {
		$user = User::fromArray(['id' => []]);
		$this->expectException(\RuntimeException::class);
		$this->connected($user)->listConversations();
	}

	public function testEmptyNativeUserIdentityIsRejected(): void {
		$user = User::fromArray(['id' => '   ']);
		$this->expectException(\RuntimeException::class);
		$this->connected($user)->getPriority();
	}

	public function testOverlongNativeUserIdentityIsRejected(): void {
		$user = User::fromArray(['id' => str_repeat('x', 192)]);
		$this->expectException(\RuntimeException::class);
		$this->connected($user)->getActiveConversation();
	}

	public function testAdministrativeUserDeletionTargetsOnlyPersistentBackend(): void {
		$user = null;
		$calls = [];
		$authenticated = $this->createStub(IAgentConversationMemory::class);
		$authenticated->method('deleteConversationsByOwner')->willReturnCallback(static function (string $type, string $id) use (&$calls): void {
			$calls[] = [$type, $id];
		});
		$this->connected($user, $authenticated)->deleteConversationsByOwner(AgentConversationScope::OWNER_USER, 'external-user');
		$this->assertSame([['user', 'external-user']], $calls);
	}

	public function testAdministrativeSessionDeletionTargetsBothBackends(): void {
		$user = null;
		$calls = [];
		$authenticated = $this->createStub(IAgentConversationMemory::class);
		$anonymous = $this->createStub(IAgentConversationMemory::class);
		$authenticated->method('deleteConversationsByOwner')->willReturnCallback(static function (string $type, string $id) use (&$calls): void {
			$calls[] = ['persistent', $type, $id];
		});
		$anonymous->method('deleteConversationsByOwner')->willReturnCallback(static function (string $type, string $id) use (&$calls): void {
			$calls[] = ['session', $type, $id];
		});
		$this->connected($user, $authenticated, $anonymous)->deleteConversationsByOwner(AgentConversationScope::OWNER_SESSION, 'session-owner');
		$this->assertSame([['persistent', 'session', 'session-owner'], ['session', 'session', 'session-owner']], $calls);
	}

	public function testUnknownOwnerTypeCannotDelete(): void {
		$user = null;
		$this->expectException(\InvalidArgumentException::class);
		$this->connected($user)->deleteConversationsByOwner('unknown', 'id');
	}

	public function testChannelDeletionTargetsBothBackends(): void {
		$user = null;
		$calls = [];
		$authenticated = $this->createStub(IAgentConversationMemory::class);
		$anonymous = $this->createStub(IAgentConversationMemory::class);
		$authenticated->method('deleteConversationsByChannel')->willReturnCallback(static function (string $channel) use (&$calls): void {
			$calls[] = 'persistent:' . $channel;
		});
		$anonymous->method('deleteConversationsByChannel')->willReturnCallback(static function (string $channel) use (&$calls): void {
			$calls[] = 'session:' . $channel;
		});
		$this->connected($user, $authenticated, $anonymous)->deleteConversationsByChannel('chatbot');
		$this->assertSame(['persistent:chatbot', 'session:chatbot'], $calls);
	}

	public function testHistoryWritesAreDelegatedOnlyToActiveBackend(): void {
		$user = null;
		$history = [];
		$anonymous = $this->createStub(IAgentConversationMemory::class);
		$anonymous->method('appendNodeHistory')->willReturnCallback(static function (string $node, array $message) use (&$history): void {
			$history[] = [$node, $message];
		});
		$this->connected($user, null, $anonymous)->appendNodeHistory('node-1', ['role' => 'user', 'content' => 'text']);
		$this->assertSame([['node-1', ['role' => 'user', 'content' => 'text']]], $history);
	}
}
