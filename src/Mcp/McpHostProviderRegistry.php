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

namespace Base3\MissionBay\Mcp;

use Base3\MissionBay\Api\IAgentPromptProvider;
use Base3\MissionBay\Api\IAgentResourceProvider;

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
