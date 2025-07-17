<?php declare(strict_types=1);

namespace MissionBay\Api;

use MissionBay\Api\IAgentResource;

/**
 * Factory interface for instantiating agent resources by type.
 */
interface IAgentResourceFactory {

	/**
	 * Creates an instance of an agent resource based on the given type name.
	 *
	 * @param string $type Type identifier of the resource (typically matches getName()).
	 * @return IAgentResource|null New instance of the resource, or null if type is unknown.
	 */
	public function createResource(string $type): ?IAgentResource;
}

