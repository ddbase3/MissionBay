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

namespace Base3\MissionBay\Test\ParserService;

use Base3\AssistantFoundation\Api\IServiceDriverDefinition;
use Base3\AssistantFoundation\Dto\ParserFileRequest;
use Base3\AssistantFoundation\Dto\ParserServiceResult;
use Base3\CoreFoundation\Api\IClassMap;
use Base3\CoreFoundation\Settings\Api\ISettingsStore;
use Base3\MissionBay\Api\IAgentConfigValueResolver;
use Base3\MissionBay\Api\IParserService;
use Base3\MissionBay\Dto\AgentContentItem;
use Base3\MissionBay\Dto\AgentParsedContent;
use Base3\MissionBay\ParserService\ConfiguredParserServiceResolver;
use Base3\MissionBay\Service\ConfiguredServiceRuntimeResolver;
use PHPUnit\Framework\TestCase;

final class ResolverTestParserServiceDriverDefinition implements IServiceDriverDefinition {

	public static function getName(): string {
		return 'resolvertestparserservicedriverdefinition';
	}

	public function getDriver(): string {
		return 'fake-parser';
	}

	public function getServiceType(): string {
		return 'parser';
	}

	public function getLabel(): string {
		return 'Fake Parser';
	}

	public function requiresConnection(): bool {
		return true;
	}

	public function getSupportedConnectionTypes(): array {
		return ['http'];
	}

	public function getImplementationInterface(): string {
		return IParserService::class;
	}

	public function getImplementationName(): string {
		return ResolverTestParserService::getName();
	}

	public function getConfigSchema(): array {
		return [];
	}

	public function getDefaultConfig(): array {
		return [
			'options' => [
				'supportedTypes' => ['file'],
				'supportedExtensions' => ['pdf'],
				'priority' => 50,
			]
		];
	}
}
