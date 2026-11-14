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

namespace Base3\MissionBay\Test\Display;

use Base3\CoreFoundation\Api\IClassMap;
use Base3\CoreFoundation\Api\IMvcView;
use Base3\CoreFoundation\Api\IRequest;
use Base3\CoreFoundation\Authorization\Api\IAuthorizationService;
use Base3\CoreFoundation\LinkTarget\Api\ILinkTargetService;
use Base3\CoreFoundation\Settings\Api\ISettingsStore;
use Base3\MissionBay\Display\AbstractServiceConfigDisplay;
use Base3\MissionBay\Display\EmbeddingConfigDisplay;
use Base3\MissionBay\Display\ImageConfigDisplay;
use Base3\MissionBay\Display\LlmConfigDisplay;
use Base3\MissionBay\Display\ParserServiceConfigDisplay;
use Base3\MissionBay\Display\SearchConfigDisplay;
use Base3\MissionBay\Display\SpeechToTextConfigDisplay;
use Base3\MissionBay\Display\TextToSpeechConfigDisplay;
use Base3\MissionBay\Display\VectorSearchConfigDisplay;
use Base3\MissionBay\Display\VectorStoreConfigDisplay;
use Base3\MissionBay\Api\IConfiguredParserServiceResolver;
use Base3\MissionBay\Api\IParserServiceTestService;
use PHPUnit\Framework\TestCase;

final class ConfiguredServiceDisplayDefinitionCoverageTest extends TestCase {

	/** @return array<class-string<AbstractServiceConfigDisplay>,array{0:string,1:string}> */
	private function definitions(): array {
		return [
			EmbeddingConfigDisplay::class => ['service-embedding', 'embedding'],
			ImageConfigDisplay::class => ['service-image', 'image'],
			LlmConfigDisplay::class => ['service-llm', 'llm'],
			ParserServiceConfigDisplay::class => ['service-parser', 'parser'],
			SearchConfigDisplay::class => ['service-search', 'search'],
			SpeechToTextConfigDisplay::class => ['service-stt', 'stt'],
			TextToSpeechConfigDisplay::class => ['service-tts', 'tts'],
			VectorSearchConfigDisplay::class => ['service-vectorsearch', 'vectorsearch'],
			VectorStoreConfigDisplay::class => ['service-vectorstore', 'vectorstore']
		];
	}

	/** @param class-string<AbstractServiceConfigDisplay> $class */
	/** @param class-string<AbstractServiceConfigDisplay> $class */
	private function display(string $class): AbstractServiceConfigDisplay {
		$view = $this->createStub(IMvcView::class);
		$request = $this->createStub(IRequest::class);
		$links = $this->createStub(ILinkTargetService::class);
		$settings = $this->createStub(ISettingsStore::class);
		$classMap = $this->createStub(IClassMap::class);
		$authorization = $this->createStub(IAuthorizationService::class);
		if ($class === ParserServiceConfigDisplay::class) {
			return new ParserServiceConfigDisplay($view, $request, $links, $settings, $classMap,
				$this->createStub(IConfiguredParserServiceResolver::class),
				$this->createStub(IParserServiceTestService::class), $authorization);
		}
		return new $class($view, $request, $links, $settings, $classMap, $authorization);
	}

	private function value(AbstractServiceConfigDisplay $display, string $method, mixed ...$arguments): mixed {
		return (new \ReflectionMethod($display, $method))->invoke($display, ...$arguments);
	}

	public function testSettingsGroupsDoNotOverlapAcrossServiceTypes(): void {
		$actual = [];
		foreach ($this->definitions() as $class => [$group, $type]) {
			$display = $this->display($class);
			$this->assertSame($group, $this->value($display, 'getSettingsGroup'));
			$this->assertSame($type, $this->value($display, 'getServiceType'));
			$this->assertSame('connection', $this->value($display, 'getConnectionGroup'));
			$actual[] = $group;
		}
		$this->assertSame(count($actual), count(array_unique($actual)));
	}

	public function testDisplayTemplatesAreOwnedByTheirConcreteClasses(): void {
		foreach ($this->definitions() as $class => $_) {
			$display = $this->display($class);
			$this->assertSame('Display/' . (new \ReflectionClass($class))->getShortName() . '.php', $this->value($display, 'getTemplate'));
			$this->assertNotSame('', $this->value($display, 'getInstancePrefix'));
		}
	}

	public function testJsonListAndSingleKeysRemainDistinct(): void {
		foreach ($this->definitions() as $class => $_) {
			$display = $this->display($class);
			$listKey = $this->value($display, 'getListDataKey');
			$singleKey = $this->value($display, 'getSingleDataKey');
			$this->assertNotSame('', $listKey);
			$this->assertNotSame('', $singleKey);
			$this->assertNotSame($listKey, $singleKey);
		}
	}

	public function testValidationMessagesIncludeTheUnknownDriver(): void {
		foreach ($this->definitions() as $class => $_) {
			$display = $this->display($class);
			$this->assertNotSame('', $this->value($display, 'getMissingIdMessage'));
			$this->assertNotSame('', $this->value($display, 'getMissingNameMessage'));
			$this->assertNotSame('', $this->value($display, 'getMissingDriverMessage'));
			$this->assertStringContainsString('unknown-provider', $this->value($display, 'getUnknownDriverMessage', 'unknown-provider'));
		}
	}

	public function testServiceSpecificDisplayOptionsDoNotDiscardRowIdentity(): void {
		foreach ($this->definitions() as $class => $_) {
			$display = $this->display($class);
			$row = $this->value($display, 'expandSpecificDisplayOptions', ['id' => 'speech-model-one', 'name' => 'Example', 'options' => []]);
			$this->assertSame('speech-model-one', $row['id']);
			$this->assertSame('Example', $row['name']);
		}
	}
}
