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
