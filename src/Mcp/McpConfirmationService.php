<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 **********************************************************************/

namespace MissionBay\Mcp;

use Base3\Logger\Api\ILogger;
use MissionBay\Api\IAgentContext;
use MissionBay\Api\IConfirmableAgentTool;

/**
 * McpConfirmationService
 *
 * Implements a synchronous, multi-call confirmation workflow for MCP tools.
 */
class McpConfirmationService {

	private const LOG_SCOPE = 'missionbay_mcp';
	public const TOOL_NAME = 'missionbay_confirm_action';

	public function __construct(
		private readonly McpConfirmationStore $store,
		private readonly ILogger $logger
	) {}

	public static function getName(): string {
		return 'mcpconfirmationservice';
	}

	/**
	 * @return array<string,mixed>
	 */
	public function getToolDefinition(): array {
		return [
			'type' => 'function',
			'label' => 'Confirm Pending Action',
			'category' => 'control',
			'tags' => ['confirmation', 'human-in-the-loop', 'control'],
			'priority' => 5,
			'function' => [
				'name' => self::TOOL_NAME,
				'description' => 'Accept or decline a pending MissionBay action that requires explicit confirmation before execution.',
				'annotations' => [
					'readOnlyHint' => false,
					'destructiveHint' => true,
					'idempotentHint' => false,
					'openWorldHint' => true
				],
				'parameters' => [
					'type' => 'object',
					'properties' => [
						'confirmation_id' => [
							'type' => 'string',
							'description' => 'Pending confirmation id returned by a previous tool call.'
						],
						'decision' => [
							'type' => 'string',
							'enum' => ['accept', 'decline'],
							'description' => 'Whether to execute or decline the pending action.'
						],
						'note' => [
							'type' => 'string',
							'description' => 'Optional note from the user or client.'
						]
					],
					'required' => ['confirmation_id', 'decision']
				],
				'outputSchema' => [
					'type' => 'object',
					'properties' => [
						'ok' => ['type' => 'boolean'],
						'confirmed' => ['type' => 'boolean'],
						'confirmation_id' => ['type' => 'string'],
						'status' => [
							'type' => 'string',
							'enum' => ['accepted', 'declined']
						],
						'message' => ['type' => 'string'],
						'tool' => ['type' => 'string'],
						'result' => new \stdClass()
					],
					'required' => ['ok', 'confirmed', 'confirmation_id', 'status']
				]
			]
		];
	}

	/**
	 * @param array<string,mixed> $arguments
	 * @return array<string,mixed>|null
	 */
	public function createPendingIfNeeded(string $profileId, string $toolName, array $arguments, McpToolCatalog $catalog, IAgentContext $context): ?array {
		$tool = $catalog->getTool($toolName);

		if(!$tool instanceof IConfirmableAgentTool) {
			return null;
		}

		$confirmation = $tool->getConfirmationRequest($toolName, $arguments, $context);

		if($confirmation === null) {
			return null;
		}

		$record = $this->store->create($profileId, $toolName, $arguments, $confirmation);

		$this->logger->logLevel(ILogger::INFO, 'MCP confirmation created.', [
			'scope' => self::LOG_SCOPE,
			'profile' => $profileId,
			'tool' => $toolName,
			'confirmation_id' => $record['id']
		]);

		return [
			'requires_confirmation' => true,
			'confirmation_id' => $record['id'],
			'tool' => $toolName,
			'title' => $record['confirmation']['title'] ?? 'Confirm tool call',
			'message' => $record['confirmation']['message'] ?? '',
			'summary' => $record['confirmation']['summary'] ?? [],
			'risk' => $record['confirmation']['risk'] ?? 'medium',
			'expires_at' => $record['expires_at'],
			'next_tool' => self::TOOL_NAME
		];
	}

	/**
	 * @param array<string,mixed> $arguments
	 * @return array<string,mixed>
	 */
	public function handleConfirmationTool(string $profileId, array $arguments, McpToolCatalog $catalog, IAgentContext $context): array {
		$confirmationId = trim((string)($arguments['confirmation_id'] ?? ''));
		$decision = strtolower(trim((string)($arguments['decision'] ?? '')));

		if($confirmationId === '') {
			throw new \InvalidArgumentException('Missing confirmation_id.');
		}

		if(!in_array($decision, ['accept', 'decline'], true)) {
			throw new \InvalidArgumentException('Invalid decision. Expected accept or decline.');
		}

		$record = $this->store->getPending($confirmationId, $profileId);

		if($decision === 'decline') {
			$this->store->mark($confirmationId, 'declined');

			return [
				'ok' => true,
				'confirmed' => false,
				'confirmation_id' => $confirmationId,
				'status' => 'declined',
				'message' => 'Pending action was declined.'
			];
		}

		$toolName = trim((string)($record['tool'] ?? ''));
		$toolArguments = $record['arguments'] ?? [];

		if($toolName === '' || !is_array($toolArguments)) {
			$this->store->mark($confirmationId, 'invalid');
			throw new \RuntimeException('Stored confirmation action is invalid.');
		}

		try {
			$result = $catalog->call($toolName, $toolArguments, $context);
			$this->store->mark($confirmationId, 'accepted');

			return [
				'ok' => true,
				'confirmed' => true,
				'confirmation_id' => $confirmationId,
				'status' => 'accepted',
				'tool' => $toolName,
				'result' => $result
			];
		}
		catch(\Throwable $e) {
			$this->store->mark($confirmationId, 'failed');
			throw $e;
		}
	}
}
