<?php declare(strict_types=1);

namespace MissionBay\Resource;

use MissionBay\Api\IAgentTool;
use MissionBay\Api\IAgentContext;

/**
 * CanvasCloseAgentTool
 *
 * Special tool: closes the chatbot canvas (UI-side).
 * The actual close action is performed by the frontend upon receiving the streamed ui-event.
 */
class CanvasCloseAgentTool extends AbstractAgentResource implements IAgentTool {

	public static function getName(): string {
		return 'canvascloseagenttool';
	}

	public function getDescription(): string {
		return 'Closes the chatbot canvas.';
	}

	public function getToolDefinitions(): array {
		return [[
			'type' => 'function',
			'label' => 'Canvas Close',
			'function' => [
				'name' => 'close_canvas',
				'description' => 'Closes the chatbot canvas panel (optionally a specific canvas id).',
				'parameters' => [
					'type' => 'object',
					'properties' => [
						'canvas_id' => [
							'type' => 'string',
							'description' => 'Canvas id to close (default: "main").'
						]
					]
				]
			]
		]];
	}

	public function callTool(string $toolName, array $arguments, IAgentContext $context): array {
		if ($toolName !== 'close_canvas') {
			throw new \InvalidArgumentException("Unsupported tool: $toolName");
		}

		$canvasId = trim((string)($arguments['canvas_id'] ?? 'main'));
		if ($canvasId === '') $canvasId = 'main';

		$stream = $context->getVar('eventstream');
		if (!$stream) {
			return [
				'ok' => false,
				'error' => 'Missing eventstream in context.'
			];
		}

		try {
			if (!$stream->isDisconnected()) {
				$stream->push('canvas.close', [
					'id' => $canvasId
				]);
			}
		} catch (\Throwable $e) {
			return [
				'ok' => false,
				'error' => 'Failed to push canvas.close: ' . $e->getMessage()
			];
		}

		return [
			'ok' => true,
			'canvas_id' => $canvasId
		];
	}
}
