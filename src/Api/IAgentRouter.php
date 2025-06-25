<?php declare(strict_types=1);

namespace MissionBay\Api;

use Base3\Api\IBase;

interface IAgentRouter extends IBase {

	/**
	 * Gibt den nächsten Node zurück, der nach dem aktuellen ausgeführt werden soll.
	 * Kann auf Basis von Output, Context oder definierten Connections entscheiden.
	 */
	public function getNextNode(string $currentNodeId, array $output, IAgentContext $context): ?string;

	/**
	 * Liefert das Input-Mapping für den nächsten Node.
	 * Verwendet Output-Daten und Input-Definitionen zur Zuordnung.
	 */
	public function mapInputs(string $fromNodeId, string $toNodeId, array $output, IAgentContext $context): array;

	/**
	 * Gibt zurück, ob für einen Node noch offene Inputs vorhanden sind.
	 * Wichtig für den Fall, dass mehrere Inputs zusammengeführt werden müssen.
	 */
	public function isReady(string $nodeId, array $currentInputs, IAgentContext $context): bool;

	/**
	 * Fügt initiale Eingabewerte für einen Node hinzu.
	 */
	public function addInitialInput(string $nodeId, string $key, mixed $value): void;

	/**
	 * Liefert initiale Inputs für alle Nodes aus einer Flow-Definition.
	 */
	public function getInitialInputs(): array;

	/**
	 * Liefert alle bekannten Connections, falls vorhanden.
	 */
	public function getConnections(): array;

	/**
	 * Fügt eine Connection hinzu.
	 */
	public function addConnection(string $fromNode, string $fromOutput, string $toNode, string $toInput, bool $mandatory = false): void;
}

