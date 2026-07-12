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

namespace MissionBay\Dto\Assistant;

use AssistantFoundation\Dto\AgentInteractionResponse;

/** Internal result of interpreting one natural-language answer to pending agent interactions. */
final class AgentInteractionResolution {

	public const STATUS_RESOLVED = 'resolved';
	public const STATUS_UNCLEAR = 'unclear';

	/**
	 * @param array<int,AgentInteractionResponse> $responses
	 * @param array<string,mixed> $metadata
	 */
	public function __construct(
		private readonly string $status,
		private readonly array $responses = [],
		private readonly string $reason = '',
		private readonly array $metadata = []
	) {
		if (!in_array($status, [self::STATUS_RESOLVED, self::STATUS_UNCLEAR], true)) {
			throw new \InvalidArgumentException('Unsupported agent interaction resolution status: ' . $status);
		}
		foreach ($responses as $response) {
			if (!$response instanceof AgentInteractionResponse) {
				throw new \InvalidArgumentException('Agent interaction resolution responses must be AgentInteractionResponse instances.');
			}
		}
		if ($status === self::STATUS_RESOLVED && $responses === []) {
			throw new \InvalidArgumentException('Resolved agent interaction resolution requires at least one response.');
		}
	}

	/** @param array<string,mixed> $metadata */
	public static function resolved(array $responses, string $reason = '', array $metadata = []): self {
		return new self(self::STATUS_RESOLVED, $responses, $reason, $metadata);
	}

	/** @param array<string,mixed> $metadata */
	public static function unclear(string $reason, array $metadata = []): self {
		return new self(self::STATUS_UNCLEAR, [], $reason, $metadata);
	}

	public function getStatus(): string { return $this->status; }
	public function isResolved(): bool { return $this->status === self::STATUS_RESOLVED; }
	public function isUnclear(): bool { return $this->status === self::STATUS_UNCLEAR; }
	/** @return array<int,AgentInteractionResponse> */
	public function getResponses(): array { return $this->responses; }
	public function getReason(): string { return $this->reason; }
	/** @return array<string,mixed> */
	public function getMetadata(): array { return $this->metadata; }

	/** @return array<string,mixed> */
	public function toArray(): array {
		return [
			'status' => $this->status,
			'responses' => array_map(
				static fn(AgentInteractionResponse $response): array => $response->toArray(),
				$this->responses
			),
			'reason' => $this->reason,
			'metadata' => $this->metadata
		];
	}
}
