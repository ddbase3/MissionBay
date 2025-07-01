<?php declare(strict_types=1);

namespace MissionBay\Api;

use MissionBay\Api\IAgentNode;

/**
 * Factory interface for instantiating agent nodes by type.
 */
interface IAgentNodeFactory {

	/**
	 * Creates an instance of an agent node based on the given type name.
	 *
	 * @param string $type Type identifier of the node (typically matches getName()).
	 * @return IAgentNode|null New instance of the node, or null if type is unknown.
	 */
	public function createNode(string $type): ?IAgentNode;
}

