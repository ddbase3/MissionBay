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

namespace Base3\MissionBay\Test\Context\Profile;

use Base3\AssistantFoundation\Api\IAgentContextContributor;
use Base3\AssistantFoundation\Dto\AgentExecutionRequest;
use Base3\AssistantFoundation\Dto\AgentInstructionBlock;
use Base3\CoreFoundation\Settings\Api\ISettingsStore;
use Base3\MissionBay\Api\IAgentComponentPresetMaterializer;
use Base3\MissionBay\Api\IAgentComponentPresetRepository;
use Base3\MissionBay\Api\IAgentResourceFactory;
use Base3\MissionBay\Context\AgentContext;
use Base3\MissionBay\Context\Profile\MissionBayContextProfileProvider;
use Base3\MissionBay\Dto\AgentComponentPresetMaterialization;
use Base3\MissionBay\Profile\AgentContextProfileResolver;
use PHPUnit\Framework\TestCase;

final class MissionBayContextProfileProviderTest extends TestCase {

	public function testProviderIdentityAndEnabledOptionsAreStable(): void {
		$provider = $this->provider([
			'profile-b' => ['label' => 'Zeta', 'enabled' => true, 'contexts' => ['second']],
			'profile-a' => ['label' => 'Alpha', 'enabled' => true, 'contexts' => ['first']],
			'profile-hidden' => ['label' => 'Hidden', 'enabled' => false, 'contexts' => ['hidden']]
		]);
		$this->assertSame('missionbaycontextprofileprovider', MissionBayContextProfileProvider::getName());
		$this->assertSame('missionbay', MissionBayContextProfileProvider::getProviderId());
		$this->assertSame(['profile-a', 'profile-b'], array_column($provider->getOptions(), 'id'));
		$this->assertTrue($provider->hasProfile('profile-a'));
		$this->assertFalse($provider->hasProfile('unknown'));
	}

	public function testDisabledProfileCannotBeMaterialized(): void {
		$provider = $this->provider(['disabled' => ['enabled' => false, 'contexts' => ['context-a']]]);
		$this->expectException(\RuntimeException::class);
		$provider->build('disabled', new AgentExecutionRequest([]));
	}

	public function testProfileWithNoPresetReturnsAnEmptyInstructionSet(): void {
		$provider = $this->provider(['empty' => ['enabled' => true, 'contexts' => []]]);
		$result = $provider->build('empty', new AgentExecutionRequest([], ['prompt' => 'hi'], ['scope' => 'user']));
		$this->assertSame('empty', $result->getProfileId());
		$this->assertSame([], $result->getBlocks());
		$this->assertSame([], $result->getWarnings());
	}

	public function testContributorsAreSortedBeforeBeingExposed(): void {
		$slow = $this->createStub(IAgentContextContributor::class);
		$slow->method('getPriority')->willReturn(50);
		$slow->method('contribute')->willReturn([new AgentInstructionBlock('z', 'later', 10, 'runtime')]);
		$fast = $this->createStub(IAgentContextContributor::class);
		$fast->method('getPriority')->willReturn(10);
		$fast->method('contribute')->willReturn([
			new AgentInstructionBlock('b', 'second', 20, 'runtime'),
			new AgentInstructionBlock('a', 'first', 10, 'runtime')
		]);
		$materializer = $this->createStub(IAgentComponentPresetMaterializer::class);
		$materializer->method('createContext')->willReturn(new AgentContext());
		$materializer->method('materialize')->willReturnCallback(function(string $id) use ($fast, $slow): AgentComponentPresetMaterialization {
			return new AgentComponentPresetMaterialization($id, [], null, null, null, $id === 'first' ? $slow : $fast, [], [' duplicate ', 'duplicate']);
		});
		$provider = $this->provider(['main' => ['enabled' => true, 'contexts' => [' first ', 'second']]], $materializer);
		$result = $provider->build('main', new AgentExecutionRequest([]));
		$this->assertSame(['a', 'b', 'z'], array_map(static fn(AgentInstructionBlock $block): string => $block->getId(), $result->getBlocks()));
		$this->assertSame(['duplicate'], $result->getWarnings());
	}

	public function testInvalidBlocksAndNonContributingPresetsYieldWarningsNotFailures(): void {
		$contributor = $this->createStub(IAgentContextContributor::class);
		$contributor->method('getPriority')->willReturn(0);
		$contributor->method('contribute')->willReturn([new AgentInstructionBlock('ok', 'text'), 'invalid']);
		$materializer = $this->createStub(IAgentComponentPresetMaterializer::class);
		$materializer->method('createContext')->willReturn(new AgentContext());
		$materializer->method('materialize')->willReturnCallback(static fn(string $id): AgentComponentPresetMaterialization => new AgentComponentPresetMaterialization($id, [], null, null, null, $id === 'valid' ? $contributor : null, []));
		$result = $this->provider(['main' => ['enabled' => true, 'contexts' => ['valid', 'none']]], $materializer)->build('main', new AgentExecutionRequest([]));
		$this->assertSame(['ok'], array_map(static fn(AgentInstructionBlock $block): string => $block->getId(), $result->getBlocks()));
		$this->assertCount(2, $result->getWarnings());
		$this->assertStringContainsString('invalid instruction block', $result->getWarnings()[0]);
		$this->assertStringContainsString('no context contributor', $result->getWarnings()[1]);
	}

	private function provider(array $profiles, ?IAgentComponentPresetMaterializer $materializer = null): MissionBayContextProfileProvider {
		$settings = $this->createStub(ISettingsStore::class);
		$settings->method('getGroup')->willReturn($profiles);
		$settings->method('get')->willReturnCallback(static fn(string $group, string $id, mixed $default = null): mixed => $profiles[$id] ?? $default);
		$resolver = new AgentContextProfileResolver(
			$settings,
			$this->createStub(IAgentComponentPresetRepository::class),
			$this->createStub(IAgentResourceFactory::class)
		);
		if ($materializer === null) {
			$materializer = $this->createStub(IAgentComponentPresetMaterializer::class);
			$materializer->method('createContext')->willReturn(new AgentContext());
		}
		return new MissionBayContextProfileProvider($resolver, $materializer);
	}
}
