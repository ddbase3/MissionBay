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
use Base3\CoreFoundation\Logger\Api\ILogger;
use Base3\MissionBay\Api\IAgentComponentPresetRepository;
use Base3\MissionBay\Api\IAgentContextFactory;
use Base3\MissionBay\Api\IAgentResource;
use Base3\MissionBay\Api\IAgentResourceFactory;
use Base3\MissionBay\Service\AgentComponentPresetMaterializer;
use PHPUnit\Framework\TestCase;

final class AgentComponentPresetMaterializerValidationTest extends TestCase {

	public function testMissingPresetIdDoesNotCreateResource(): void {
		$materializer = $this->materializer([]);
		$result = $materializer->materialize('  ', $this->context());
		$this->assertSame('', $result->getPresetId());
		$this->assertNull($result->getResource());
		$this->assertSame([], $result->getCapabilities());
		$this->assertFalse($result->isReady());
		$this->assertStringContainsString('preset id is missing', $result->getWarnings()[0]);
	}

	public function testUnknownPresetIsReportedWithoutSynthesizingResource(): void {
		$result = $this->materializer([])->materialize('unknown', $this->context());
		$this->assertNull($result->getResource());
		$this->assertStringContainsString('preset not found', $result->getWarnings()[0]);
	}

	public function testDisabledPresetVariantsDoNotMaterialize(): void {
		foreach ([false, 0, 'OFF', ' no ', 'FALSE'] as $disabled) {
			$result = $this->materializer(['disabled' => ['type' => 'tool', 'enabled' => $disabled]])->materialize('disabled', $this->context());
			$this->assertNull($result->getResource());
			$this->assertStringContainsString('disabled', $result->getWarnings()[0]);
		}
	}

	public function testMissingResourceTypeReturnsExplicitWarningAndUnsupportedDeclaredCapability(): void {
		$result = $this->materializer(['missing-type' => ['capabilities' => ' Tool, context, TOOL, unknown '] ])
			->materialize('missing-type', $this->context());
		$this->assertNull($result->getResource());
		$this->assertSame(['context', 'tool'], $result->getCapabilities());
		$this->assertCount(3, $result->getWarnings());
		$this->assertStringContainsString('has no resource type', $result->getWarnings()[0]);
		$this->assertFalse($result->isReady());
	}

	public function testUnknownResourceFactoryTypeProducesWarningAndKeepsPresetMetadata(): void {
		$preset = ['type' => 'not-installed', 'label' => 'Broken resource'];
		$result = $this->materializer(['broken' => $preset])->materialize('broken', $this->context());
		$this->assertSame($preset, $result->getPreset());
		$this->assertNull($result->getResource());
		$this->assertStringContainsString('could not be instantiated', $result->getWarnings()[0]);
	}

	public function testValidResourceWithoutClaimsMaterializesSuccessfully(): void {
		$resource = $this->createStub(IAgentResource::class);
		$resource->method('getId')->willReturn('preset_simple');
		$result = $this->materializer(['simple' => ['type' => 'simple']], $resource)->materialize('simple', $this->context());
		$this->assertSame($resource, $result->getResource());
		$this->assertTrue($result->isReady());
		$this->assertSame([], $result->getWarnings());
		$this->assertNull($result->getTool());
		$this->assertNull($result->getMemory());
	}

	public function testUnresolvedDockIsReportedRatherThanConstructed(): void {
		$resource = $this->createStub(IAgentResource::class);
		$resource->method('getId')->willReturn('preset_parent');
		$result = $this->materializer([
			'parent' => ['type' => 'simple', 'docks' => ['tools' => ['missing']]]
		], $resource)->materialize('parent', $this->context());
		$this->assertFalse($result->isReady());
		$this->assertSame([], $result->getDocks());
		$this->assertStringContainsString('Dock target is not a known preset', $result->getWarnings()[0]);
	}

	public function testCircularDockReferencesAreNotRecursivelyExpanded(): void {
		$resource = $this->createStub(IAgentResource::class);
		$resource->method('getId')->willReturn('preset_self');
		$result = $this->materializer([
			'self' => ['type' => 'simple', 'docks' => ['other' => 'self']]
		], $resource)->materialize('self', $this->context());
		$this->assertFalse($result->isReady());
		$this->assertStringContainsString('Circular component preset dock reference', $result->getWarnings()[0]);
	}

	public function testWarningsDoNotLeakBetweenIndependentMaterializations(): void {
		$resource = $this->createStub(IAgentResource::class);
		$materializer = $this->materializer(['valid' => ['type' => 'simple']], $resource);
		$this->assertNotEmpty($materializer->materialize('missing', $this->context())->getWarnings());
		$this->assertSame([], $materializer->materialize('valid', $this->context())->getWarnings());
	}

	public function testContextCreationPreservesProvidedVarsAndDefaultSource(): void {
		$seen = [];
		$context = $this->context();
		$contextFactory = $this->createStub(IAgentContextFactory::class);
		$contextFactory->method('createContext')->willReturnCallback(static function(string $type, mixed $memory, array $vars) use (&$seen, $context): IAgentContext {
			$seen = [$type, $memory, $vars];
			return $context;
		});
		$materializer = $this->materializer([], null, $contextFactory);
		$this->assertSame($context, $materializer->createContext(['request_id' => 'r1']));
		$this->assertSame('agentcontext', $seen[0]);
		$this->assertNull($seen[1]);
		$this->assertSame(['source' => 'agent-component-preset-materializer', 'request_id' => 'r1'], $seen[2]);
	}

	/** @param array<string,array<string,mixed>> $presets */
	private function materializer(array $presets, ?IAgentResource $resource = null, ?IAgentContextFactory $contextFactory = null): AgentComponentPresetMaterializer {
		$repository = $this->createStub(IAgentComponentPresetRepository::class);
		$repository->method('getPreset')->willReturnCallback(static fn(string $id, array $default = []): array => $presets[$id] ?? $default);
		$repository->method('hasPreset')->willReturnCallback(static fn(string $id): bool => array_key_exists($id, $presets));
		$factory = $this->createStub(IAgentResourceFactory::class);
		$factory->method('createResource')->willReturn($resource);
		return new AgentComponentPresetMaterializer(
			$repository,
			$factory,
			$contextFactory ?? $this->createStub(IAgentContextFactory::class),
			$this->createStub(ILogger::class)
		);
	}

	private function context(): IAgentContext {
		return $this->createStub(IAgentContext::class);
	}
}
