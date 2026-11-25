<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0
 * https://github.com/ddbase3/MissionBay
 **********************************************************************/

namespace Base3\MissionBay\Test\Resource;

use Base3\AssistantFoundation\Api\IAgentContextContributor;
use Base3\MissionBay\Api\IAgentResource;
use Base3\MissionBay\Api\IAgentTool;
use Base3\MissionBay\Resource\AgentContext\Focus\FocusAgentResource;
use Base3\MissionBay\Resource\AgentContext\UserPrefs\UserPrefsAgentResource;
use Base3\MissionBay\Resource\Mcp\McpClientAgentResource;
use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;

final class ResourceDiscoveryLayoutTest extends TestCase {

	public function testResourceNamesAndNamespacesMatchDiscoveredPaths(): void {
		$root = dirname(__DIR__, 2) . '/src/Resource';
		$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
		$names = [];
		$count = 0;

		foreach ($files as $file) {
			if (!$file->isFile() || $file->getExtension() !== 'php') {
				continue;
			}

			$relative = substr($file->getPathname(), strlen($root) + 1);
			$className = 'Base3\\MissionBay\\Resource\\' . str_replace('/', '\\', substr($relative, 0, -4));
			$this->assertTrue(class_exists($className), 'Class not loadable at: ' . $relative);

			$reflection = new ReflectionClass($className);
			$this->assertSame($file->getRealPath(), $reflection->getFileName(), $className);
			if ($reflection->isAbstract()) {
				continue;
			}

			$this->assertTrue($reflection->implementsInterface(IAgentResource::class), $className);
			$name = $className::getName();
			$this->assertSame(strtolower($reflection->getShortName()), $name, $className);
			$this->assertArrayNotHasKey($name, $names, 'Duplicate class-map name: ' . $name);
			$names[$name] = $className;
			$count++;
		}

		$this->assertCount(53, $names);
		$this->assertSame(53, $count);
	}

	public function testOnlyGenericChatModelResourcesRemain(): void {
		$directories = glob(dirname(__DIR__, 2) . '/src/Resource/ChatModel/*', GLOB_ONLYDIR);
		$this->assertSame(['Configured', 'Generic', 'Routing'], array_map('basename', $directories));
	}

	public function testMultiRoleResourcesKeepTheirInterfacesWithoutProxies(): void {
		foreach ([FocusAgentResource::class, UserPrefsAgentResource::class, McpClientAgentResource::class] as $className) {
			$this->assertTrue(is_subclass_of($className, IAgentTool::class), $className);
			$this->assertTrue(is_subclass_of($className, IAgentContextContributor::class), $className);
		}
	}
}
