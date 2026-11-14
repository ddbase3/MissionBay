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

namespace Base3\MissionBay\Test\Profile;

use Base3\AssistantFoundation\Api\IAgentContext;
use Base3\AssistantFoundation\Api\IAgentContextContributor;
use Base3\AssistantFoundation\Api\IAgentConversationMemory;
use Base3\AssistantFoundation\Dto\AgentConversation;
use Base3\AssistantFoundation\Dto\AgentConversationScope;
use Base3\AssistantFoundation\Dto\AgentInstructionBlock;
use Base3\CoreFoundation\Settings\Api\ISettingsStore;
use Base3\MissionBay\Api\IAgentComponentPresetRepository;
use Base3\MissionBay\Api\IAgentConfigValueResolver;
use Base3\MissionBay\Api\IAgentResource;
use Base3\MissionBay\Api\IAgentResourceFactory;
use Base3\MissionBay\Profile\AgentContextProfileResolver;
use Base3\MissionBay\Profile\AgentMemoryProfileResolver;
use Base3\MissionBay\Resource\AbstractAgentResource;
use Base3\MissionBay\Resource\AgentContext\Text\StaticTextContextAgentResource;
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
