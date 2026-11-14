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
use Base3\CoreFoundation\Accesscontrol\Api\IAccesscontrol;
use Base3\CoreFoundation\Database\Api\IDatabase;
use Base3\CoreFoundation\Session\Api\ISession;
use Base3\CoreFoundation\Usermanager\Api\IUsermanager;
use Base3\MissionBay\Api\IAgentConfigValueResolver;
use Base3\MissionBay\Resource\FocusAgentResource;
use PHPUnit\Framework\TestCase;

final class FocusAgentResourceValidationTest extends TestCase {

	public function testConstructorCreatesTheEstablishedFocusTable(): void {
		$queries = [];
		$db = $this->createStub(IDatabase::class);
		$db->method('nonQuery')->willReturnCallback(static function(string $query) use (&$queries): void {
			$queries[] = $query;
		});
		$this->resource($db);
		$this->assertCount(1, $queries);
		$this->assertStringContainsString('CREATE TABLE IF NOT EXISTS base3_missionbay_focus_state', $queries[0]);
	}

	public function testDefinitionsRequireApprovalForBothMutations(): void {
		$definitions = $this->resource()->getToolDefinitions();
		$this->assertSame(['set_focus', 'clear_focus'], array_map(static fn(array $d): string => $d['function']['name'], $definitions));
		foreach($definitions as $definition) {
			$this->assertTrue($definition['mutation']);
			$this->assertTrue($definition['requiresApproval']);
			$this->assertFalse($definition['readOnlyHint']);
		}
	}

	public function testDisabledResourceHasNoToolDefinitions(): void {
		$resource = $this->resource();
		$resource->setConfig(['enabled' => false]);
		$this->assertSame([], $resource->getToolDefinitions());
	}

	public function testDisabledResourceDoesNotReadFocusState(): void {
		$db = $this->createMock(IDatabase::class);
		$db->expects($this->never())->method('singleQuery');
		$resource = $this->resource($db);
		$resource->setConfig(['enabled' => false]);
		$this->assertSame([], $resource->contribute($this->context()));
	}

	public function testDisabledResourceRejectsWrites(): void {
		$resource = $this->resource();
		$resource->setConfig(['enabled' => 'no']);
		$this->assertSame(['error' => 'Focus resource is disabled'],
			$resource->callTool('set_focus', ['focus' => 'test'], $this->context()));
	}

	public function testUnknownToolNameIsRejected(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->resource()->callTool('remove_everything', [], $this->context());
	}

	public function testNonScalarFocusIsRejectedBeforeIdentityLookup(): void {
		$db = $this->createMock(IDatabase::class);
		$db->expects($this->never())->method('singleQuery');
		$resource = $this->resource($db);
		$this->assertSame(['error' => 'Focus must be a scalar string'],
			$resource->callTool('set_focus', ['focus' => ['not', 'scalar']], $this->context()));
	}

	public function testEmptyFocusIsRejectedBeforeIdentityLookup(): void {
		$db = $this->createMock(IDatabase::class);
		$db->expects($this->never())->method('singleQuery');
		$this->assertSame(['error' => 'Missing parameter: focus'],
			$this->resource($db)->callTool('set_focus', ['focus' => '  <b>  </b> '], $this->context()));
	}

	public function testPriorityIsClampedToValidRange(): void {
		$resource = $this->resource();
		$resource->setConfig(['priority' => 10000]);
		$this->assertSame(1000, $resource->getPriority());
		$resource->setConfig(['priority' => -20]);
		$this->assertSame(0, $resource->getPriority());
	}

	public function testFocusLengthCannotFallBelowTwenty(): void {
		$resource = $this->resource();
		$resource->setConfig(['maxfocuslength' => 1]);
		$definition = $resource->getToolDefinitions()[0];
		$this->assertSame(20, $definition['function']['parameters']['properties']['focus']['maxLength']);
	}

	public function testFocusLengthCannotExceedOneThousand(): void {
		$resource = $this->resource();
		$resource->setConfig(['maxfocuslength' => 9999]);
		$definition = $resource->getToolDefinitions()[0];
		$this->assertSame(1000, $definition['function']['parameters']['properties']['focus']['maxLength']);
	}

	public function testToolPriorityIsConfiguredIndependentlyOfContextPriority(): void {
		$resource = $this->resource();
		$resource->setConfig(['priority' => 15, 'toolpriority' => 80]);
		$this->assertSame(15, $resource->getPriority());
		$this->assertSame(80, $resource->getToolDefinitions()[0]['priority']);
	}

	private function resource(?IDatabase $database = null): FocusAgentResource {
		$resolver = $this->createStub(IAgentConfigValueResolver::class);
		$resolver->method('resolveValue')->willReturnCallback(static fn(mixed $value): mixed => $value);
		return new FocusAgentResource(
			$database ?? $this->createStub(IDatabase::class),
			$resolver,
			$this->createStub(IAccesscontrol::class),
			$this->createStub(ISession::class),
			$this->createStub(IUsermanager::class),
			'focus-unit'
		);
	}

	private function context(): IAgentContext {
		return $this->createStub(IAgentContext::class);
	}
}
