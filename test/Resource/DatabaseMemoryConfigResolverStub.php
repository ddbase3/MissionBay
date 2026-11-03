<?php declare(strict_types=1);

namespace MissionBay\Test\Resource;

use AssistantFoundation\Dto\AgentConversationScope;
use Base3\Api\IClassMap;
use Base3\Usermanager\Api\IUsermanager;
use Base3\Usermanager\User;
use Base3\Crypt\Sodium\SodiumCrypt;
use CredentialFoundation\Api\IEncryptionProfileService;
use CredentialFoundation\Dto\EncryptionKeyMaterial;
use Base3\Database\Api\IDatabase;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Context\AgentContext;
use MissionBay\Resource\DatabaseMemoryAgentResource;
use PHPUnit\Framework\TestCase;

final class DatabaseMemoryConfigResolverStub implements IAgentConfigValueResolver {
	public function resolveValue(array|string|int|float|bool|null $config): mixed {
		if (is_array($config) && ($config['mode'] ?? null) === 'fixed') {
			return $config['value'] ?? null;
		}
		return $config;
	}
}
