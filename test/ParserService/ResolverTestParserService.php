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

final class ResolverTestParserService implements IParserService {

	/** @var array<string,mixed> */
	private array $options = [];

	public static function getName(): string {
		return 'resolvertestparserservice';
	}

	public function setOptions(array $options): void {
		$this->options = array_merge($this->options, $options);
	}

	public function getOptions(): array {
		return $this->options;
	}

	public function getPriority(): int {
		return (int)($this->options['priority'] ?? 50);
	}

	public function supportsFile(ParserFileRequest $request): bool {
		return true;
	}

	public function parseFile(ParserFileRequest $request): ParserServiceResult {
		return new ParserServiceResult('parsed');
	}

	public function supports(AgentContentItem $item): bool {
		return true;
	}

	public function parse(AgentContentItem $item): AgentParsedContent {
		return new AgentParsedContent('parsed');
	}
}
