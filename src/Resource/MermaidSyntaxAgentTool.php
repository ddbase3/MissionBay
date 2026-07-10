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

namespace MissionBay\Resource;

use Base3\Logger\Api\ILogger;
use MissionBay\Agent\AgentNodeDock;
use AssistantFoundation\Api\IAgentContext;
use MissionBay\Api\IAgentTool;
use stdClass;

class MermaidSyntaxAgentTool extends AbstractAgentResource implements IAgentTool {

	private ?ILogger $logger = null;

	public static function getName(): string {
		return 'mermaidsyntaxagenttool';
	}

	public function getDescription(): string {
		return 'Provides compact Mermaid syntax guidance, templates, and basic type detection for supported diagram types.';
	}

	public function getDockDefinitions(): array {
		return [
			new AgentNodeDock(
				name: 'logger',
				description: 'Optional logger for Mermaid syntax tool events.',
				interface: ILogger::class,
				maxConnections: 1,
				required: false
			)
		];
	}

	public function init(array $resources, IAgentContext $context): void {
		if (!empty($resources['logger'][0]) && $resources['logger'][0] instanceof ILogger) {
			$this->logger = $resources['logger'][0];
			$this->log('logger docked into MermaidSyntaxAgentTool');
		}
	}

	public function getToolDefinitions(): array {
		return [[
			'type' => 'function',
			'label' => 'List Supported Mermaid Types',
			'category' => 'syntax',
			'tags' => ['mermaid', 'diagram', 'syntax', 'types'],
			'priority' => 70,
			'function' => [
				'name' => 'list_supported_mermaid_types',
				'description' => 'Lists the Mermaid diagram types supported by this helper tool.',
				'parameters' => [
					'type' => 'object',
					'properties' => new stdClass(),
					'additionalProperties' => false
				]
			]
		], [
			'type' => 'function',
			'label' => 'Detect Existing Mermaid Type',
			'category' => 'syntax',
			'tags' => ['mermaid', 'diagram', 'syntax', 'detect'],
			'priority' => 75,
			'function' => [
				'name' => 'detect_existing_mermaid_type',
				'description' => 'Detects the Mermaid diagram type from existing Mermaid code using simple prefix rules.',
				'parameters' => [
					'type' => 'object',
					'properties' => [
						'code' => [
							'type' => 'string',
							'description' => 'Existing Mermaid code to inspect.'
						]
					],
					'required' => ['code'],
					'additionalProperties' => false
				]
			]
		], [
			'type' => 'function',
			'label' => 'Get Mermaid Type Guide',
			'category' => 'syntax',
			'tags' => ['mermaid', 'diagram', 'syntax', 'guide'],
			'priority' => 80,
			'function' => [
				'name' => 'get_mermaid_type_guide',
				'description' => 'Returns compact syntax guidance for one supported Mermaid diagram type.',
				'parameters' => [
					'type' => 'object',
					'properties' => [
						'type' => [
							'type' => 'string',
							'description' => 'Supported Mermaid type, for example flowchart, sequenceDiagram, pie, or xychart.'
						]
					],
					'required' => ['type'],
					'additionalProperties' => false
				]
			]
		], [
			'type' => 'function',
			'label' => 'Get Mermaid Template',
			'category' => 'syntax',
			'tags' => ['mermaid', 'diagram', 'syntax', 'template'],
			'priority' => 85,
			'function' => [
				'name' => 'get_mermaid_template',
				'description' => 'Returns a minimal valid Mermaid starter template for one supported diagram type.',
				'parameters' => [
					'type' => 'object',
					'properties' => [
						'type' => [
							'type' => 'string',
							'description' => 'Supported Mermaid type.'
						],
						'title' => [
							'type' => 'string',
							'description' => 'Optional title to insert into the template where applicable.'
						],
						'direction' => [
							'type' => 'string',
							'description' => 'Optional direction for flowchart, for example TD, LR, RL, or BT.'
						],
						'orientation' => [
							'type' => 'string',
							'description' => 'Optional xychart orientation: horizontal or vertical.'
						],
						'show_data' => [
							'type' => 'boolean',
							'description' => 'Optional pie chart flag. If true, uses pie showData.'
						]
					],
					'required' => ['type'],
					'additionalProperties' => false
				]
			]
		]];
	}

	public function callTool(string $name, array $arguments, IAgentContext $context): array {
		$this->log('tool call ' . $name . ' args=' . $this->encodeForLog($this->summarizeArguments($name, $arguments)));

		$result = match ($name) {
			'list_supported_mermaid_types' => $this->toolListSupportedMermaidTypes(),
			'detect_existing_mermaid_type' => $this->toolDetectExistingMermaidType($arguments),
			'get_mermaid_type_guide' => $this->toolGetMermaidTypeGuide($arguments),
			'get_mermaid_template' => $this->toolGetMermaidTemplate($arguments),
			default => throw new \InvalidArgumentException("Unsupported tool: $name")
		};

		$this->log('tool result ' . $name . ' summary=' . $this->encodeForLog($this->summarizeResult($name, $result)));

		return $result;
	}

	private function toolListSupportedMermaidTypes(): array {
		$definitions = $this->getTypeDefinitions();
		$types = [];

		foreach ($definitions as $type => $definition) {
			$types[] = [
				'type' => $type,
				'start_keyword' => $definition['start_keyword'],
				'summary' => $definition['summary']
			];
		}

		return [
			'count' => count($types),
			'types' => array_values($types)
		];
	}

	private function toolDetectExistingMermaidType(array $arguments): array {
		$code = trim((string)($arguments['code'] ?? ''));
		if ($code === '') {
			return ['error' => 'Missing parameter: code'];
		}

		$type = $this->detectTypeFromCode($code);

		return [
			'type' => $type,
			'detected' => $type !== null
		];
	}

	private function toolGetMermaidTypeGuide(array $arguments): array {
		$type = $this->normalizeType((string)($arguments['type'] ?? ''));
		if ($type === null) {
			return [
				'error' => 'Unsupported Mermaid type.',
				'supported_types' => array_keys($this->getTypeDefinitions())
			];
		}

		$definition = $this->getTypeDefinitions()[$type];

		return [
			'type' => $type,
			'start_keyword' => $definition['start_keyword'],
			'summary' => $definition['summary'],
			'required_elements' => $definition['required_elements'],
			'recommended_elements' => $definition['recommended_elements'],
			'forbidden_patterns' => $definition['forbidden_patterns'],
			'example' => $definition['example']
		];
	}

	private function toolGetMermaidTemplate(array $arguments): array {
		$type = $this->normalizeType((string)($arguments['type'] ?? ''));
		if ($type === null) {
			return [
				'error' => 'Unsupported Mermaid type.',
				'supported_types' => array_keys($this->getTypeDefinitions())
			];
		}

		$title = trim((string)($arguments['title'] ?? ''));
		$direction = strtoupper(trim((string)($arguments['direction'] ?? 'TD')));
		$orientation = strtolower(trim((string)($arguments['orientation'] ?? 'horizontal')));
		$showData = (bool)($arguments['show_data'] ?? false);

		$template = $this->buildTemplate($type, $title, $direction, $orientation, $showData);

		return [
			'type' => $type,
			'template' => $template
		];
	}

	private function detectTypeFromCode(string $code): ?string {
		$code = ltrim($code);

		$patterns = [
			'/^flowchart\b/i' => 'flowchart',
			'/^graph\b/i' => 'flowchart',
			'/^sequenceDiagram\b/i' => 'sequenceDiagram',
			'/^classDiagram\b/i' => 'classDiagram',
			'/^stateDiagram-v2\b/i' => 'stateDiagram-v2',
			'/^stateDiagram\b/i' => 'stateDiagram-v2',
			'/^erDiagram\b/i' => 'erDiagram',
			'/^journey\b/i' => 'journey',
			'/^gantt\b/i' => 'gantt',
			'/^pie\b/i' => 'pie',
			'/^xychart(?:\s+horizontal)?\b/i' => 'xychart'
		];

		foreach ($patterns as $pattern => $type) {
			if (preg_match($pattern, $code)) {
				return $type;
			}
		}

		return null;
	}

	private function normalizeType(string $type): ?string {
		$type = trim($type);
		if ($type === '') {
			return null;
		}

		$map = [
			'graph' => 'flowchart',
			'flowchart' => 'flowchart',
			'sequencediagram' => 'sequenceDiagram',
			'classdiagram' => 'classDiagram',
			'statediagram-v2' => 'stateDiagram-v2',
			'statediagram' => 'stateDiagram-v2',
			'erdiagram' => 'erDiagram',
			'journey' => 'journey',
			'gantt' => 'gantt',
			'pie' => 'pie',
			'xychart' => 'xychart'
		];

		$key = strtolower($type);

		return $map[$key] ?? null;
	}

	private function buildTemplate(string $type, string $title, string $direction, string $orientation, bool $showData): string {
		$title = $this->escapeQuotedValue($title);

		return match ($type) {
			'flowchart' => $this->buildFlowchartTemplate($direction),
			'sequenceDiagram' => $this->buildSequenceDiagramTemplate(),
			'classDiagram' => $this->buildClassDiagramTemplate(),
			'stateDiagram-v2' => $this->buildStateDiagramTemplate(),
			'erDiagram' => $this->buildErDiagramTemplate(),
			'journey' => $this->buildJourneyTemplate($title),
			'gantt' => $this->buildGanttTemplate($title),
			'pie' => $this->buildPieTemplate($title, $showData),
			'xychart' => $this->buildXyChartTemplate($title, $orientation),
			default => ''
		};
	}

	private function buildFlowchartTemplate(string $direction): string {
		$allowedDirections = ['TD', 'LR', 'RL', 'BT'];
		if (!in_array($direction, $allowedDirections, true)) {
			$direction = 'TD';
		}

		return implode("\n", [
			'flowchart ' . $direction,
			'	A[Start] --> B[Next step]',
			'	B --> C[Done]'
		]);
	}

	private function buildSequenceDiagramTemplate(): string {
		return implode("\n", [
			'sequenceDiagram',
			'	participant User',
			'	participant System',
			'	User->>System: Request',
			'	System-->>User: Response'
		]);
	}

	private function buildClassDiagramTemplate(): string {
		return implode("\n", [
			'classDiagram',
			'	class Animal {',
			'		+String name',
			'		+move()',
			'	}',
			'	class Dog {',
			'		+bark()',
			'	}',
			'	Animal <|-- Dog'
		]);
	}

	private function buildStateDiagramTemplate(): string {
		return implode("\n", [
			'stateDiagram-v2',
			'	[*] --> Idle',
			'	Idle --> Running: Start',
			'	Running --> Idle: Stop'
		]);
	}

	private function buildErDiagramTemplate(): string {
		return implode("\n", [
			'erDiagram',
			'	USER ||--o{ ORDER : places',
			'	USER {',
			'		int id',
			'		string name',
			'	}',
			'	ORDER {',
			'		int id',
			'		string status',
			'	}'
		]);
	}

	private function buildJourneyTemplate(string $title): string {
		$lines = ['journey'];

		if ($title !== '') {
			$lines[] = '	title ' . $title;
		} else {
			$lines[] = '	title Example journey';
		}

		$lines[] = '	section Main flow';
		$lines[] = '	Discover feature: 3: User';
		$lines[] = '	Use feature: 5: User';
		$lines[] = '	Share feedback: 4: User';

		return implode("\n", $lines);
	}

	private function buildGanttTemplate(string $title): string {
		$lines = ['gantt'];

		if ($title !== '') {
			$lines[] = '	title ' . $title;
		} else {
			$lines[] = '	title Example project';
		}

		$lines[] = '	dateFormat YYYY-MM-DD';
		$lines[] = '	section Planning';
		$lines[] = '	Research :a1, 2026-01-01, 7d';
		$lines[] = '	Build :a2, after a1, 10d';

		return implode("\n", $lines);
	}

	private function buildPieTemplate(string $title, bool $showData): string {
		$header = $showData ? 'pie showData' : 'pie';
		$lines = [$header];

		if ($title !== '') {
			$lines[] = '	title ' . $title;
		} else {
			$lines[] = '	title Example distribution';
		}

		$lines[] = '	"Group A" : 40';
		$lines[] = '	"Group B" : 35';
		$lines[] = '	"Group C" : 25';

		return implode("\n", $lines);
	}

	private function buildXyChartTemplate(string $title, string $orientation): string {
		$header = $orientation === 'vertical' ? 'xychart' : 'xychart horizontal';
		$lines = [$header];

		if ($title !== '') {
			$lines[] = '	title "' . $title . '"';
		} else {
			$lines[] = '	title "Example chart"';
		}

		$lines[] = '	x-axis ["A", "B", "C"]';
		$lines[] = '	y-axis "Value" 0 --> 100';
		$lines[] = '	bar [30, 55, 80]';

		return implode("\n", $lines);
	}

	private function escapeQuotedValue(string $value): string {
		return str_replace(['\\', '"'], ['\\\\', '\"'], $value);
	}

	private function summarizeArguments(string $name, array $arguments): array {
		return match ($name) {
			'list_supported_mermaid_types' => [],
			'detect_existing_mermaid_type' => [
				'code_len' => strlen((string)($arguments['code'] ?? '')),
				'code_preview' => $this->shortenForLog((string)($arguments['code'] ?? ''), 160)
			],
			'get_mermaid_type_guide' => [
				'type' => (string)($arguments['type'] ?? '')
			],
			'get_mermaid_template' => [
				'type' => (string)($arguments['type'] ?? ''),
				'title' => $this->shortenForLog((string)($arguments['title'] ?? ''), 120),
				'direction' => (string)($arguments['direction'] ?? ''),
				'orientation' => (string)($arguments['orientation'] ?? ''),
				'show_data' => (bool)($arguments['show_data'] ?? false)
			],
			default => $arguments
		};
	}

	private function summarizeResult(string $name, array $result): array {
		if (isset($result['error'])) {
			return [
				'error' => (string)$result['error']
			];
		}

		return match ($name) {
			'list_supported_mermaid_types' => [
				'count' => (int)($result['count'] ?? 0)
			],
			'detect_existing_mermaid_type' => [
				'type' => $result['type'] ?? null,
				'detected' => (bool)($result['detected'] ?? false)
			],
			'get_mermaid_type_guide' => [
				'type' => (string)($result['type'] ?? ''),
				'start_keyword' => (string)($result['start_keyword'] ?? '')
			],
			'get_mermaid_template' => [
				'type' => (string)($result['type'] ?? ''),
				'template_len' => strlen((string)($result['template'] ?? '')),
				'template_preview' => $this->shortenForLog((string)($result['template'] ?? ''), 160)
			],
			default => []
		};
	}

	private function shortenForLog(string $value, int $maxLength): string {
		$value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);

		if ($value === '') {
			return '';
		}

		if (mb_strlen($value) <= $maxLength) {
			return $value;
		}

		return mb_substr($value, 0, $maxLength - 3) . '...';
	}

	private function encodeForLog(array $data): string {
		$json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

		return $json !== false ? $json : '{"error":"log_encoding_failed"}';
	}

	private function getTypeDefinitions(): array {
		return [
			'flowchart' => [
				'start_keyword' => 'flowchart',
				'summary' => 'General-purpose node and edge diagrams.',
				'required_elements' => [
					'First line must start with flowchart plus a direction such as TD or LR.',
					'Use nodes and edges such as A[Text] --> B[Text].'
				],
				'recommended_elements' => [
					'Prefer short readable node labels.',
					'Use one consistent direction.'
				],
				'forbidden_patterns' => [
					'Do not mix with sequenceDiagram, classDiagram, pie, gantt, or xychart syntax.',
					'Do not return only edges without the flowchart header.'
				],
				'example' => implode("\n", [
					'flowchart TD',
					'	A[Start] --> B[Check]',
					'	B --> C[Done]'
				])
			],
			'sequenceDiagram' => [
				'start_keyword' => 'sequenceDiagram',
				'summary' => 'Message flow between participants over time.',
				'required_elements' => [
					'First line must be sequenceDiagram.',
					'Use participant declarations and message arrows.'
				],
				'recommended_elements' => [
					'Name participants clearly.',
					'Use ->> and -->> consistently.'
				],
				'forbidden_patterns' => [
					'Do not use flowchart node syntax like A[Text].',
					'Do not use pie or xychart statements.'
				],
				'example' => implode("\n", [
					'sequenceDiagram',
					'	participant User',
					'	participant API',
					'	User->>API: Request',
					'	API-->>User: Response'
				])
			],
			'classDiagram' => [
				'start_keyword' => 'classDiagram',
				'summary' => 'Classes, members, and inheritance relationships.',
				'required_elements' => [
					'First line must be classDiagram.',
					'Use class blocks or class declarations plus relations.'
				],
				'recommended_elements' => [
					'Use clear class names.',
					'Keep relation types explicit.'
				],
				'forbidden_patterns' => [
					'Do not use flowchart arrows as the main syntax.',
					'Do not mix with erDiagram entity blocks.'
				],
				'example' => implode("\n", [
					'classDiagram',
					'	class Animal {',
					'		+String name',
					'	}',
					'	class Dog',
					'	Animal <|-- Dog'
				])
			],
			'stateDiagram-v2' => [
				'start_keyword' => 'stateDiagram-v2',
				'summary' => 'States and transitions for a process or lifecycle.',
				'required_elements' => [
					'First line should be stateDiagram-v2.',
					'Use state transitions such as A --> B.'
				],
				'recommended_elements' => [
					'Use [*] for start or end states where helpful.',
					'Keep transition labels short.'
				],
				'forbidden_patterns' => [
					'Do not use participant messages from sequenceDiagram.',
					'Do not use ER entity definitions.'
				],
				'example' => implode("\n", [
					'stateDiagram-v2',
					'	[*] --> Idle',
					'	Idle --> Running: Start',
					'	Running --> Idle: Stop'
				])
			],
			'erDiagram' => [
				'start_keyword' => 'erDiagram',
				'summary' => 'Entity-relationship diagrams with entities and cardinalities.',
				'required_elements' => [
					'First line must be erDiagram.',
					'Use relationships and entity field blocks.'
				],
				'recommended_elements' => [
					'Use uppercase entity names for readability.',
					'Keep field types simple.'
				],
				'forbidden_patterns' => [
					'Do not use classDiagram or flowchart blocks.',
					'Do not use x-axis, y-axis, or bar.'
				],
				'example' => implode("\n", [
					'erDiagram',
					'	USER ||--o{ ORDER : places',
					'	USER {',
					'		int id',
					'		string name',
					'	}'
				])
			],
			'journey' => [
				'start_keyword' => 'journey',
				'summary' => 'User journey diagrams with sections and scored steps.',
				'required_elements' => [
					'First line must be journey.',
					'Use title, section, and scored steps.'
				],
				'recommended_elements' => [
					'Group steps by section.',
					'Use short natural-language labels.'
				],
				'forbidden_patterns' => [
					'Do not use flowchart arrows.',
					'Do not use xychart data arrays.'
				],
				'example' => implode("\n", [
					'journey',
					'	title Signup journey',
					'	section Main flow',
					'	Visit page: 3: User',
					'	Create account: 5: User'
				])
			],
			'gantt' => [
				'start_keyword' => 'gantt',
				'summary' => 'Project timeline charts with dated tasks.',
				'required_elements' => [
					'First line must be gantt.',
					'Use dateFormat and at least one section with tasks.'
				],
				'recommended_elements' => [
					'Use explicit dates or after-dependencies.',
					'Keep task labels concise.'
				],
				'forbidden_patterns' => [
					'Do not use flowchart nodes or sequence participants.',
					'Do not use bar arrays.'
				],
				'example' => implode("\n", [
					'gantt',
					'	title Example project',
					'	dateFormat YYYY-MM-DD',
					'	section Planning',
					'	Research :a1, 2026-01-01, 7d'
				])
			],
			'pie' => [
				'start_keyword' => 'pie',
				'summary' => 'Pie charts for proportional distributions.',
				'required_elements' => [
					'First line must be pie or pie showData.',
					'Use quoted labels with numeric values.'
				],
				'recommended_elements' => [
					'Add a title line.',
					'Keep the number of slices moderate.'
				],
				'forbidden_patterns' => [
					'Do not use x-axis, y-axis, bar, or line.',
					'Do not use flowchart arrows.'
				],
				'example' => implode("\n", [
					'pie showData',
					'	title Example distribution',
					'	"Group A" : 40',
					'	"Group B" : 35',
					'	"Group C" : 25'
				])
			],
			'xychart' => [
				'start_keyword' => 'xychart',
				'summary' => 'Bar and line charts using x-axis and numeric series.',
				'required_elements' => [
					'First line must be xychart or xychart horizontal.',
					'Use x-axis and at least one series such as bar [...] or line [...].'
				],
				'recommended_elements' => [
					'Use y-axis with a clear label and numeric range.',
					'Use quoted x-axis labels.'
				],
				'forbidden_patterns' => [
					'Do not start with bar alone.',
					'Do not use lines such as Country: 123.',
					'Do not use flowchart arrows.'
				],
				'example' => implode("\n", [
					'xychart horizontal',
					'	title "Top countries"',
					'	x-axis ["Russia", "Canada", "China"]',
					'	y-axis "Area" 0 --> 18000000',
					'	bar [17098242, 9984670, 9596961]'
				])
			]
		];
	}

	private function log(string $message): void {
		if ($this->logger) {
			$this->logger->log(static::getName(), '[' . $this->id . '] ' . $message);
		}
	}
}
