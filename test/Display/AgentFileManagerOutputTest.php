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

namespace Base3\MissionBay\Test\Display;

use Base3\AssistantFoundation\Api\IAgentConfigurationService;
use Base3\CoreFoundation\Api\IRequest;
use Base3\CoreFoundation\Authorization\Api\IAuthorizationService;
use Base3\CoreFoundation\Authorization\Dto\AuthorizationDecision;
use Base3\CoreFoundation\Translation\Api\ITranslation;
use Base3\CoreFoundation\Usermanager\Api\IUsermanager;
use Base3\CoreFoundation\Usermanager\User;
use Base3\ClientStack\FileManager\FileManagerHttpService;
use Base3\ClientStack\FileManager\FileUploadService;
use Base3\MissionBay\Output\AgentFileManagerOutput;
use PHPUnit\Framework\TestCase;
use Base3\ResourceFoundation\Api\IFileStorage;
use Base3\ResourceFoundation\Api\IManagedFileStorageService;

final class AgentFileManagerOutputTest extends TestCase {

	protected function setUp(): void {
		http_response_code(200);
	}

	private function createAgentFileManagerOutput(
		AuthorizationDecision $decision,
		bool $agentExists,
		IManagedFileStorageService $storage,
		array $query = ['fm_group' => 'agent', 'fm_name' => 'agent-123', 'fm_action' => 'list'],
		string $actorId = 'actor-5'
	): AgentFileManagerOutput {
		$request = $this->createMock(IRequest::class);
		$request->method('get')->willReturnCallback(static fn(string $key, mixed $default = null): mixed => $query[$key] ?? $default);
		$request->method('server')->willReturn('POST');
		$request->method('getJsonBody')->willReturn(['path' => '']);
		$agents = $this->createMock(IAgentConfigurationService::class);
		$agents->method('hasAgent')->willReturn($agentExists);
		$authorization = $this->createMock(IAuthorizationService::class);
		$authorization->method('getDefaultAuthority')->willReturn('host-instance');
		$authorization->method('decide')->willReturn($decision);
		$users = $this->createMock(IUsermanager::class);
		$users->method('getUser')->willReturn($actorId !== '' ? User::fromArray(['id' => $actorId]) : null);
		$translation = $this->createStub(ITranslation::class);
		$translation->method('translate')->willReturn('Request failed.');
		$uploads = new FileUploadService($storage, sys_get_temp_dir() . '/test-agent-file-upload');
		$http = new FileManagerHttpService($storage, $uploads, $request, $translation);
		return new AgentFileManagerOutput($request, $users, $authorization, $agents, $http, $translation);
	}

	public function testAgentResourceOwnerIsTheCanonicalAgentIdOnEveryRead(): void {
		$files = $this->createMock(IFileStorage::class);
		$files->method('list')->with('')->willReturn([]);
		$storage = $this->createMock(IManagedFileStorageService::class);
		$storage->expects(self::once())->method('openOrCreate')
			->with('agent', 'agent-123', 'collection')->willReturn($files);
		$payload = $this->createAgentFileManagerOutput(AuthorizationDecision::allow(), true, $storage)->getOutput('php', true);
		self::assertSame([], json_decode($payload, true, 512, JSON_THROW_ON_ERROR)['items']);
		self::assertSame(hash('sha256', "agent\0agent-123\0collection"),
			json_decode($payload, true, 512, JSON_THROW_ON_ERROR)['containerId']);
	}

	public function testUnauthorizedActorCannotReachStorage(): void {
		$storage = $this->createMock(IManagedFileStorageService::class);
		$storage->expects(self::never())->method('openOrCreate');
		$body = $this->createAgentFileManagerOutput(AuthorizationDecision::deny(), true, $storage)->getOutput();
		self::assertSame(403, http_response_code());
		self::assertArrayHasKey('message', json_decode($body, true));
	}

	public function testUnknownAgentCannotReachStorage(): void {
		$storage = $this->createMock(IManagedFileStorageService::class);
		$storage->expects(self::never())->method('openOrCreate');
		$this->createAgentFileManagerOutput(AuthorizationDecision::allow(), false, $storage)->getOutput();
		self::assertSame(404, http_response_code());
	}

	public function testDifferentOwnerGroupCannotReachAgentCollection(): void {
		$storage = $this->createMock(IManagedFileStorageService::class);
		$storage->expects(self::never())->method('openOrCreate');
		$this->createAgentFileManagerOutput(AuthorizationDecision::allow(), true, $storage,
			['fm_group' => 'other', 'fm_name' => 'agent-123', 'fm_action' => 'list'])->getOutput();
		self::assertSame(404, http_response_code());
	}

	public function testUnauthenticatedActorCannotReachStorage(): void {
		$storage = $this->createMock(IManagedFileStorageService::class);
		$storage->expects(self::never())->method('openOrCreate');
		$this->createAgentFileManagerOutput(AuthorizationDecision::allow(), true, $storage,
			['fm_group' => 'agent', 'fm_name' => 'agent-123', 'fm_action' => 'list'], '')->getOutput();
		self::assertSame(403, http_response_code());
	}
}
