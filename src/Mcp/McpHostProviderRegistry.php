<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 **********************************************************************/

namespace MissionBay\Mcp;

use MissionBay\Api\IAgentPromptProvider;
use MissionBay\Api\IAgentResourceProvider;

/**
 * McpHostProviderRegistry
 *
 * Allows host endpoints to explicitly register request-local resource and
 * prompt providers without coupling MissionBay to a concrete host system.
 */
class McpHostProviderRegistry {

	/**
	 * @var IAgentResourceProvider[]
	 */
	private static array $resourceProviders = [];

	/**
	 * @var IAgentPromptProvider[]
	 */
	private static array $promptProviders = [];

	public static function getName(): string {
		return 'mcphostproviderregistry';
	}

	public static function addResourceProvider(IAgentResourceProvider $provider): void {
		self::$resourceProviders[] = $provider;
	}

	/**
	 * @return IAgentResourceProvider[]
	 */
	public static function getResourceProviders(): array {
		return self::$resourceProviders;
	}

	public static function addPromptProvider(IAgentPromptProvider $provider): void {
		self::$promptProviders[] = $provider;
	}

	/**
	 * @return IAgentPromptProvider[]
	 */
	public static function getPromptProviders(): array {
		return self::$promptProviders;
	}
}
