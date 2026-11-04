<?php declare(strict_types=1);

namespace MissionBay\Test\Resource;

use Base3\Logger\Api\ILogger;
use MissionBay\Context\AgentContext;
use MissionBay\Resource\MermaidSyntaxAgentTool;
use PHPUnit\Framework\TestCase;

final class MermaidSyntaxAgentToolBehaviorTest extends TestCase {

	public function testToolCatalogIsReadOnlyAndHasFourDistinctFunctions(): void {
		$tools = (new MermaidSyntaxAgentTool('mermaid-test'))->getToolDefinitions();
		$this->assertCount(4, $tools);
		$this->assertSame(['list_supported_mermaid_types', 'detect_existing_mermaid_type', 'get_mermaid_type_guide', 'get_mermaid_template'], array_column(array_column($tools, 'function'), 'name'));
		foreach ($tools as $tool) {
			$this->assertTrue($tool['readOnlyHint']);
			$this->assertFalse($tool['mutation']);
			$this->assertFalse($tool['requiresApproval']);
		}
	}

	public function testSupportedTypeCatalogListsNineCanonicalTypes(): void {
		$result = $this->call('list_supported_mermaid_types');
		$this->assertSame(9, $result['count']);
		$this->assertSame(['flowchart', 'sequenceDiagram', 'classDiagram', 'stateDiagram-v2', 'erDiagram', 'journey', 'gantt', 'pie', 'xychart'], array_column($result['types'], 'type'));
		foreach ($result['types'] as $type) {
			$this->assertNotEmpty($type['start_keyword']);
			$this->assertNotEmpty($type['summary']);
		}
	}

	public function testUnknownFunctionIsRejected(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->call('run_mermaid');
	}

	public function testDetectionRequiresNonemptyCode(): void {
		$this->assertSame(['error' => 'Missing parameter: code'], $this->call('detect_existing_mermaid_type'));
		$this->assertSame(['error' => 'Missing parameter: code'], $this->call('detect_existing_mermaid_type', ['code' => "  \n  "]));
	}

	public function testFlowchartAndLegacyGraphPrefixesMapToFlowchart(): void {
		foreach (['flowchart LR\nA-->B', "  graph TD\nA-->B", 'FLOWCHART TD'] as $code) {
			$this->assertSame(['type' => 'flowchart', 'detected' => true], $this->call('detect_existing_mermaid_type', ['code' => $code]));
		}
	}

	public function testDetectionRecognizesEverySupportedDiagramHeader(): void {
		$headers = ['sequenceDiagram' => 'sequenceDiagram', 'classDiagram' => 'classDiagram', 'stateDiagram' => 'stateDiagram-v2', 'stateDiagram-v2' => 'stateDiagram-v2', 'erDiagram' => 'erDiagram', 'journey' => 'journey', 'gantt' => 'gantt', 'pie showData' => 'pie', 'xychart horizontal' => 'xychart'];
		foreach ($headers as $header => $type) {
			$this->assertSame(['type' => $type, 'detected' => true], $this->call('detect_existing_mermaid_type', ['code' => $header]));
		}
	}

	public function testDetectionDoesNotGuessFromUnrelatedBodyLines(): void {
		$this->assertSame(['type' => null, 'detected' => false], $this->call('detect_existing_mermaid_type', ['code' => 'A --> B']));
		$this->assertSame(['type' => null, 'detected' => false], $this->call('detect_existing_mermaid_type', ['code' => 'bar [10,20]']));
	}

	public function testEveryGuideHasRequiredAndForbiddenSyntaxInformation(): void {
		$types = array_column($this->call('list_supported_mermaid_types')['types'], 'type');
		foreach ($types as $type) {
			$guide = $this->call('get_mermaid_type_guide', ['type' => $type]);
			$this->assertSame($type, $guide['type']);
			$this->assertNotEmpty($guide['required_elements']);
			$this->assertNotEmpty($guide['recommended_elements']);
			$this->assertNotEmpty($guide['forbidden_patterns']);
			$this->assertStringContainsString($guide['start_keyword'], $guide['example']);
		}
	}

	public function testGuideNormalizesAliasesAndCase(): void {
		$this->assertSame('flowchart', $this->call('get_mermaid_type_guide', ['type' => ' GRAPH '])['type']);
		$this->assertSame('stateDiagram-v2', $this->call('get_mermaid_type_guide', ['type' => 'stateDiagram'])['type']);
		$this->assertSame('sequenceDiagram', $this->call('get_mermaid_type_guide', ['type' => 'SEQUENCEDIAGRAM'])['type']);
	}

	public function testUnknownGuideTypeReturnsSupportedChoices(): void {
		$result = $this->call('get_mermaid_type_guide', ['type' => 'not-mermaid']);
		$this->assertSame('Unsupported Mermaid type.', $result['error']);
		$this->assertContains('pie', $result['supported_types']);
	}

	public function testMissingGuideTypeReturnsStructuredError(): void {
		$this->assertSame('Unsupported Mermaid type.', $this->call('get_mermaid_type_guide')['error']);
	}

	public function testMissingTemplateTypeReturnsStructuredError(): void {
		$this->assertSame('Unsupported Mermaid type.', $this->call('get_mermaid_template')['error']);
	}

	public function testEveryTemplateBeginsWithCanonicalHeader(): void {
		foreach (array_column($this->call('list_supported_mermaid_types')['types'], 'type') as $type) {
			$result = $this->call('get_mermaid_template', ['type' => $type]);
			$this->assertSame($type, $result['type']);
			$this->assertStringStartsWith($type, $result['template']);
		}
	}

	public function testFlowchartDirectionAcceptsKnownValues(): void {
		foreach (['TD', 'LR', 'RL', 'BT'] as $direction) {
			$result = $this->call('get_mermaid_template', ['type' => 'graph', 'direction' => strtolower($direction)]);
			$this->assertStringStartsWith('flowchart ' . $direction, $result['template']);
		}
	}

	public function testInvalidFlowchartDirectionUsesDefault(): void {
		$this->assertStringStartsWith('flowchart TD', $this->call('get_mermaid_template', ['type' => 'flowchart', 'direction' => 'diagonal'])['template']);
	}

	public function testPieShowDataFlagControlsHeader(): void {
		$this->assertStringStartsWith('pie showData', $this->call('get_mermaid_template', ['type' => 'pie', 'show_data' => true])['template']);
		$this->assertStringStartsWith("pie\n", $this->call('get_mermaid_template', ['type' => 'pie', 'show_data' => false])['template']);
	}

	public function testXyChartOrientationControlsHeader(): void {
		$this->assertStringStartsWith("xychart\n", $this->call('get_mermaid_template', ['type' => 'xychart', 'orientation' => 'vertical'])['template']);
		$this->assertStringStartsWith('xychart horizontal', $this->call('get_mermaid_template', ['type' => 'xychart', 'orientation' => 'other'])['template']);
	}

	public function testTitleIsEscapedInsideXyChartString(): void {
		$template = $this->call('get_mermaid_template', ['type' => 'xychart', 'title' => 'A "quote" and \\ slash'])['template'];
		$this->assertStringContainsString('title "A \\"quote\\" and \\\\ slash"', $template);
	}

	public function testJourneyGanttAndPieHonorProvidedTitle(): void {
		foreach (['journey', 'gantt', 'pie'] as $type) {
			$this->assertStringContainsString('title Example heading', $this->call('get_mermaid_template', ['type' => $type, 'title' => 'Example heading'])['template']);
		}
	}

	public function testJourneyGanttPieAndChartContainDefaultsWithoutTitle(): void {
		foreach (['journey' => 'Example journey', 'gantt' => 'Example project', 'pie' => 'Example distribution', 'xychart' => 'Example chart'] as $type => $title) {
			$this->assertStringContainsString($title, $this->call('get_mermaid_template', ['type' => $type])['template']);
		}
	}

	public function testToolHasOptionalLoggerDock(): void {
		$docks = (new MermaidSyntaxAgentTool())->getDockDefinitions();
		$this->assertCount(1, $docks);
		$this->assertSame('logger', $docks[0]->name);
	}

	public function testCallsCanBeMadeWithoutLogger(): void {
		$this->assertSame('flowchart', $this->call('get_mermaid_template', ['type' => 'graph'])['type']);
	}

	public function testLoggerReceivesSummariesNotFullTemplate(): void {
		$messages = [];
		$logger = $this->createStub(ILogger::class);
		$logger->method('log')->willReturnCallback(static function(string $scope, string $message, ?int $timestamp = null) use (&$messages): bool {
			$messages[] = [$scope, $message];
			return true;
		});
		$tool = new MermaidSyntaxAgentTool('mermaid-check');
		$tool->init(['logger' => [$logger]], new AgentContext());
		$tool->callTool('get_mermaid_template', ['type' => 'flowchart'], new AgentContext());
		$this->assertNotEmpty($messages);
		$this->assertSame('mermaidsyntaxagenttool', $messages[0][0]);
		$this->assertStringContainsString('mermaid-check', $messages[0][1]);
		$this->assertStringContainsString('template_len', $messages[count($messages) - 1][1]);
	}

	private function call(string $name, array $args = []): array {
		return (new MermaidSyntaxAgentTool('mermaid-test'))->callTool($name, $args, new AgentContext());
	}
}
