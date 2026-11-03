<?php declare(strict_types=1);

namespace MissionBay\Test\ParserService;

use AssistantFoundation\Api\IServiceDriverDefinition;
use AssistantFoundation\Dto\ParserFileRequest;
use AssistantFoundation\Dto\ParserServiceResult;
use Base3\Api\IClassMap;
use Base3\Settings\Api\ISettingsStore;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Api\IParserService;
use MissionBay\Dto\AgentContentItem;
use MissionBay\Dto\AgentParsedContent;
use MissionBay\ParserService\ConfiguredParserServiceResolver;
use MissionBay\Service\ConfiguredServiceRuntimeResolver;
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
