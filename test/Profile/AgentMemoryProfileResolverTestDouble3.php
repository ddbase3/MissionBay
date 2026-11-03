<?php declare(strict_types=1);

namespace MissionBay\Test\Profile;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentContextContributor;
use AssistantFoundation\Api\IAgentConversationMemory;
use AssistantFoundation\Dto\AgentConversation;
use AssistantFoundation\Dto\AgentConversationScope;
use AssistantFoundation\Dto\AgentInstructionBlock;
use Base3\Settings\Api\ISettingsStore;
use MissionBay\Api\IAgentComponentPresetRepository;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Api\IAgentResource;
use MissionBay\Api\IAgentResourceFactory;
use MissionBay\Profile\AgentContextProfileResolver;
use MissionBay\Profile\AgentMemoryProfileResolver;
use MissionBay\Resource\AbstractAgentResource;
use MissionBay\Resource\AgentContext\Text\StaticTextContextAgentResource;
use PHPUnit\Framework\TestCase;

final class AgentMemoryProfileResolverTestDouble3 implements IAgentResourceFactory  {
			public function createResource(string $type): ?IAgentResource {
				return match ($type) {
					'testconversationmemory' => new AgentMemoryProfileResolverTestDouble3Double1('conversation'),
					'testcontextcontributor', 'testdualtoolcontext' => new AgentMemoryProfileResolverTestDouble3Double2('context'),
					'statictextcontextagentresource' => new StaticTextContextAgentResource(
						new AgentMemoryProfileResolverTestDouble3Double3(),
						'static-text'
					),
					default => null
				};
			}
		}

require_once __DIR__ . '/AgentMemoryProfileResolverTestDouble3Double1.php';

require_once __DIR__ . '/AgentMemoryProfileResolverTestDouble3Double2.php';

require_once __DIR__ . '/AgentMemoryProfileResolverTestDouble3Double3.php';
