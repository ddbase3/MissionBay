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

use Base3\AssistantFoundation\Dto\AgentConversationScope;
use Base3\CoreFoundation\Api\IClassMap;
use Base3\CoreFoundation\Usermanager\Api\IUsermanager;
use Base3\CoreFoundation\Usermanager\User;
use Base3\Core\Crypt\Sodium\SodiumCrypt;
use Base3\CredentialFoundation\Api\IEncryptionProfileService;
use Base3\CredentialFoundation\Dto\EncryptionKeyMaterial;
use Base3\CoreFoundation\Database\Api\IDatabase;
use Base3\MissionBay\Api\IAgentConfigValueResolver;
use Base3\MissionBay\Context\AgentContext;
use Base3\MissionBay\Resource\DatabaseMemoryAgentResource;
use PHPUnit\Framework\TestCase;

final class DatabaseMemoryConfigResolverStub implements IAgentConfigValueResolver {
	public function resolveValue(array|string|int|float|bool|null $config): mixed {
		if (is_array($config) && ($config['mode'] ?? null) === 'fixed') {
			return $config['value'] ?? null;
		}
		return $config;
	}
}
