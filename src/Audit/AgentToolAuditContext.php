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

namespace MissionBay\Audit;

use AssistantFoundation\Api\IAgentContext;

/**
 * AgentToolAuditContext
 *
 * Carries transport-neutral metadata for the next configured tool call.
 * The execution wrapper consumes this metadata when emitting audit events.
 */
final class AgentToolAuditContext {

	public const CONTEXT_KEY = 'missionbay.agent.tool_audit.current';

	public const SOURCE_AGENT = 'agent';
	public const SOURCE_MCP = 'mcp';
	public const SOURCE_DIRECT = 'direct';

	/**
	 * @return array<string,mixed>
	 */
	public static function read(IAgentContext $context): array {
		$value = $context->getVar(self::CONTEXT_KEY);
		return is_array($value) ? $value : [];
	}

	/**
	 * Replaces the current audit metadata and returns the previous value.
	 *
	 * @param array<string,mixed> $metadata
	 */
	public static function push(IAgentContext $context, array $metadata): mixed {
		$previous = $context->getVar(self::CONTEXT_KEY);
		$context->setVar(self::CONTEXT_KEY, $metadata);
		return $previous;
	}

	public static function restore(IAgentContext $context, mixed $previous): void {
		if ($previous === null) {
			$context->forgetVar(self::CONTEXT_KEY);
			return;
		}

		$context->setVar(self::CONTEXT_KEY, $previous);
	}

	public static function generateCallId(string $prefix = 'toolcall'): string {
		$prefix = trim($prefix);
		if ($prefix === '') {
			$prefix = 'toolcall';
		}

		try {
			return $prefix . '-' . bin2hex(random_bytes(16));
		}
		catch (\Throwable) {
			return $prefix . '-' . sha1(uniqid('', true));
		}
	}
}
