<?php declare(strict_types=1);

namespace MissionBay\Api;

use Base3\Api\IBase;

interface IAgentFlow extends IBase {

	// Kontext setzen
	public function setContext(IAgentContext $context): void;

	// Flow ausführen
	public function run(array $inputs): array;

	// Nodes und Verbindungen
	public function addNode(IAgentNode $node): void;
	public function addConnection(string $fromNode, string $fromOutput, string $toNode, string $toInput): void;

	// Optional: Initialwerte setzen
	public function addInitialInput(string $nodeId, string $key, mixed $value): void;

	// Routing-Logik
	public function getNextNode(string $currentNodeId, array $output): ?string;
	public function mapInputs(string $fromNodeId, string $toNodeId, array $output): array;
	public function isReady(string $nodeId, array $currentInputs): bool;

	// Zugriff auf Status
	public function getInitialInputs(): array;
	public function getConnections(): array;
}

