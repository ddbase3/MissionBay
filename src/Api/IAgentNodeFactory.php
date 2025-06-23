<?php declare(strict_types=1);

namespace MissionBay\Api;

use MissionBay\Api\IAgentNode;

interface IAgentNodeFactory {

	/**
	 * Erzeugt eine IAgentNode-Instanz anhand des Typnamens.
	 */
	public function createNode(string $type): ?IAgentNode;
}

