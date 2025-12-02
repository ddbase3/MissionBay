<?php declare(strict_types=1);

namespace MissionBay\Resource;

use MissionBay\Api\IAgentTool;
use MissionBay\Api\IAgentContext;

class HelloWorldCanvasAgentTool extends AbstractAgentResource implements IAgentTool {

	public static function getName(): string {
		return 'helloworldcanvasagenttool';
	}

	public function getDescription(): string {
		return 'Demo tool that opens a canvas and renders two HTML blocks.';
	}

	public function getToolDefinitions(): array {
		return [[
			'type' => 'function',
			'label' => 'Hello World Canvas',
			'function' => [
				'name' => 'hello_world_canvas',
				'description' => 'Opens the chatbot canvas and renders a hello-world demo (two HTML blocks).',
				'parameters' => [
					'type' => 'object',
					'properties' => [
						'canvas_id' => [
							'type' => 'string',
							'description' => 'Target canvas id (default: "main").'
						],
						'title' => [
							'type' => 'string',
							'description' => 'Canvas title (default: "Hello Canvas").'
						],
						'open' => [
							'type' => 'boolean',
							'description' => 'Whether to open/focus the canvas (default: true).'
						]
					]
				]
			]
		]];
	}

	public function callTool(string $toolName, array $arguments, IAgentContext $context): array {
		if ($toolName !== 'hello_world_canvas') {
			throw new \InvalidArgumentException("Unsupported tool: $toolName");
		}

		$canvasId = trim((string)($arguments['canvas_id'] ?? 'main'));
		if ($canvasId === '') $canvasId = 'main';

		$title = trim((string)($arguments['title'] ?? 'Hello Canvas'));
		if ($title === '') $title = 'Hello Canvas';

		$open = $arguments['open'] ?? true;
		$open = filter_var($open, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
		if ($open === null) $open = true;

		$stream = $context->getVar('eventstream');
		if (!$stream) {
			return [
				'ok' => false,
				'error' => 'Missing eventstream in context.'
			];
		}

		$blocks = [
			[
				'type' => 'html',
				'html' => '<div class="canvas-card"><h2>Hello World 👋</h2><p>Das ist Block #1 aus einem Tool.</p></div>',
				'sanitize' => true
			],
			[
				'type' => 'html',
				'html' => '<div class="canvas-card"><h3>Block #2</h3><ul><li>HTML Block</li><li>aus MissionBay Tool</li></ul></div>',
				'sanitize' => true
			]
		];

		try {
			if ($open && !$stream->isDisconnected()) {
				$stream->push('canvas.open', [
					'id' => $canvasId,
					'title' => $title,
					'focus' => true
				]);
			}

			if (!$stream->isDisconnected()) {
				$stream->push('canvas.render', [
					'id' => $canvasId,
					'mode' => 'replace',
					'title' => $title,
					'blocks' => $blocks
				]);
			}
		} catch (\Throwable $e) {
			return [
				'ok' => false,
				'error' => 'Failed to push canvas events: ' . $e->getMessage()
			];
		}

		return [
			'ok' => true,
			'canvas_id' => $canvasId
		];
	}
}

