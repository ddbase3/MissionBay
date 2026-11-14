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
use Base3\AssistantFoundation\Api\IAgentContextContributor;
use Base3\CoreFoundation\Api\ISchemaProvider;
use Base3\MissionBay\Api\IAgentConfigValueResolver;
use Base3\MissionBay\Resource\AgentContext\Text\StaticTextContextAgentResource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\Base3\MissionBay\Resource\AgentContext\Text\StaticTextContextAgentResource::class)]
final class StaticTextContextAgentResourceTest extends TestCase {

	public function testContractsNameAndDescription(): void {
		$resource = new StaticTextContextAgentResource($this->resolver(), 'preset-context');

		$this->assertInstanceOf(IAgentContextContributor::class, $resource);
		$this->assertInstanceOf(ISchemaProvider::class, $resource);
		$this->assertSame('statictextcontextagentresource', StaticTextContextAgentResource::getName());
		$this->assertSame(
			'Contributes configurable static text to the agent system context.',
			$resource->getDescription()
		);
	}

	public function testSchemaProvidesRequiredMultilineTextAndPriority(): void {
		$resource = new StaticTextContextAgentResource($this->resolver(), 'preset-context');
		$schema = $resource->getSchema();

		$this->assertSame(['text'], $schema['required']);
		$this->assertSame('string', $schema['properties']['text']['type']);
		$this->assertSame('textarea', $schema['properties']['text']['x-ui']['control']);
		$this->assertSame(12, $schema['properties']['text']['x-ui']['rows']);
		$this->assertSame(30, $schema['properties']['priority']['default']);
	}

	public function testContributePreservesMultilineTextAndMetadata(): void {
		$resource = new StaticTextContextAgentResource($this->resolver(), 'support-rules');
		$resource->setConfig([
			'text' => "Answer in German.\nDo not expose internal identifiers.",
			'priority' => 44
		]);

		$blocks = [...$resource->contribute($this->createStub(IAgentContext::class))];

		$this->assertSame(44, $resource->getPriority());
		$this->assertCount(1, $blocks);
		$this->assertSame('static-text-context:support-rules', $blocks[0]->getId());
		$this->assertSame("Answer in German.\nDo not expose internal identifiers.", $blocks[0]->getContent());
		$this->assertSame('support-rules', $blocks[0]->getSource());
		$this->assertSame(
			['implementation' => 'statictextcontextagentresource'],
			$blocks[0]->getMetadata()
		);
		$this->assertSame([
			'role' => 'system',
			'content' => "Answer in German.\nDo not expose internal identifiers."
		], $blocks[0]->toMessage());
	}

	public function testConfigValuesAreResolvedAndPriorityIsClamped(): void {
		$resolver = $this->resolver([
			json_encode(['mode' => 'fixed', 'value' => 'Resolved context']) => 'Resolved context',
			'priority-spec' => 1500
		]);
		$resource = new StaticTextContextAgentResource($resolver, 'resolved-context');
		$resource->setConfig([
			'text' => ['mode' => 'fixed', 'value' => 'Resolved context'],
			'priority' => 'priority-spec'
		]);

		$blocks = [...$resource->contribute($this->createStub(IAgentContext::class))];

		$this->assertSame(1000, $resource->getPriority());
		$this->assertSame('Resolved context', $blocks[0]->getContent());
	}

	public function testEmptyTextDoesNotContributeABlock(): void {
		$resource = new StaticTextContextAgentResource($this->resolver(), 'empty-context');
		$resource->setConfig(['text' => " \n\t "]);

		$this->assertSame([], [...$resource->contribute($this->createStub(IAgentContext::class))]);
	}

	public function testBlockIdsAreUniquePerConfiguredResource(): void {
		$left = new StaticTextContextAgentResource($this->resolver(), 'left-context');
		$right = new StaticTextContextAgentResource($this->resolver(), 'right-context');
		$left->setConfig(['text' => 'Left']);
		$right->setConfig(['text' => 'Right']);

		$context = $this->createStub(IAgentContext::class);
		$leftBlock = [...$left->contribute($context)][0];
		$rightBlock = [...$right->contribute($context)][0];

		$this->assertNotSame($leftBlock->getId(), $rightBlock->getId());
	}

	/**
	 * @param array<string,mixed> $map
	 */
	private function resolver(array $map = []): IAgentConfigValueResolver {
		return new StaticTextContextAgentResourceTestDouble1($map);
	}
}

require_once __DIR__ . '/StaticTextContextAgentResourceTestDouble1.php';
