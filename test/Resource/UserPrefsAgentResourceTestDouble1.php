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

namespace Base3\MissionBay\Test\Resource;

use PHPUnit\Framework\TestCase;
use Base3\CoreFoundation\Accesscontrol\Api\IAccesscontrol;
use Base3\CoreFoundation\Database\Api\IDatabase;
use Base3\CoreFoundation\Logger\Api\ILogger;
use Base3\CoreFoundation\Session\Api\ISession;
use Base3\MissionBay\Api\IAgentConfigValueResolver;
use Base3\AssistantFoundation\Api\IAgentContext;
use Base3\MissionBay\Api\IAgentMutationGuardedTool;
use Base3\MissionBay\Resource\UserPrefsAgentResource;

final class UserPrefsAgentResourceTestDouble1 implements IAgentConfigValueResolver {
			private array $map;

			public function __construct(array $map) {
				$this->map = $map;
			}

			public function resolveValue(array|string|int|float|bool|null $config): mixed {
				$key = is_array($config) ? json_encode($config) : (string)$config;
				if (array_key_exists($key, $this->map)) {
					return $this->map[$key];
				}
				return $config;
			}
		}
