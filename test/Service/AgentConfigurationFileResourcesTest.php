<?php declare(strict_types=1);

namespace MissionBay\Test\Service;

use AssistantFoundation\Api\IAgentDefinition;
use Base3\Api\IClassMap;
use Base3\Settings\Api\ISettingsStore;
use ClientStack\FileManager\FileUploadService;
use MissionBay\Service\AgentConfigurationService;
use PHPUnit\Framework\TestCase;
use ResourceFoundation\Api\IManagedFileStorageService;
use RuntimeException;
use LogicException;

final class AgentConfigurationFileResourcesTest extends TestCase {

	public function testDeletingAnAgentDeletesOnlyItsCanonicalFileOwnerFirst(): void {
		$id = '550e8400-e29b-41d4-a716-446655440000';
		$events = [];
		$settings = $this->createMock(ISettingsStore::class);
		$settings->expects(self::once())->method('remove')->with('agent', $id)
			->willReturnCallback(static function() use (&$events): void { $events[] = 'remove-settings'; });
		$settings->expects(self::once())->method('save');
		$storage = $this->createMock(IManagedFileStorageService::class);
		$storage->expects(self::once())->method('has')->with('agent', $id, IManagedFileStorageService::MODE_COLLECTION)->willReturn(true);
		$storage->expects(self::once())->method('delete')->with('agent', $id, IManagedFileStorageService::MODE_COLLECTION)
			->willReturnCallback(static function() use (&$events): void { $events[] = 'delete-storage'; });
		$classmap = $this->createMock(IClassMap::class);
		$classmap->method('getInstancesByInterface')->willReturn([]);

		(new AgentConfigurationService($settings, $classmap, $storage))->deleteAgent($id);
		self::assertSame(['delete-storage', 'remove-settings'], $events);
	}


	public function testDeletingAgentRemovesUnfinishedUploadSessionsUnderTheSameOwner(): void {
		$id = '550e8400-e29b-41d4-a716-446655440000';
		$storage = $this->createMock(IManagedFileStorageService::class);
		$storage->method('has')->willReturn(false);
		$root = sys_get_temp_dir() . '/missionbay-agent-upload-' . bin2hex(random_bytes(5));
		$uploads = new FileUploadService($storage, $root);
		$session = $uploads->start('agent', $id, IManagedFileStorageService::MODE_COLLECTION,
			'actor', 1024, ['name' => 'draft.pdf', 'size' => 0, 'chunkSize' => 1024, 'totalChunks' => 1]);
		$uploads->status('agent', $id, IManagedFileStorageService::MODE_COLLECTION, 'actor', $session['uploadId']);

		$settings = $this->createMock(ISettingsStore::class);
		$settings->expects(self::once())->method('remove')->with('agent', $id);
		$classmap = $this->createMock(IClassMap::class);
		$classmap->method('getInstancesByInterface')->willReturn([]);
		(new AgentConfigurationService($settings, $classmap, $storage, $uploads))->deleteAgent($id);

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Upload session was not found.');
		$uploads->status('agent', $id, IManagedFileStorageService::MODE_COLLECTION, 'actor', $session['uploadId']);
	}

	public function testStorageDeletionFailureKeepsAgentConfigurationUntouched(): void {
		$id = '550e8400-e29b-41d4-a716-446655440000';
		$settings = $this->createMock(ISettingsStore::class);
		$settings->expects(self::never())->method('remove');
		$settings->expects(self::never())->method('save');
		$storage = $this->createMock(IManagedFileStorageService::class);
		$storage->method('has')->willReturn(true);
		$storage->expects(self::once())->method('delete')->willThrowException(new RuntimeException('Provider cleanup failed'));
		$classmap = $this->createMock(IClassMap::class);
		$classmap->method('getInstancesByInterface')->willReturn([]);

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Provider cleanup failed');
		(new AgentConfigurationService($settings, $classmap, $storage))->deleteAgent($id);
	}

	public function testFixedAgentCannotBeDeletedOrHaveItsFilesRemoved(): void {
		$definition = $this->createMock(IAgentDefinition::class);
		$definition->method('getId')->willReturn('fixed-agent');
		$definition->method('getLabel')->willReturn('Fixed');
		$classmap = $this->createMock(IClassMap::class);
		$classmap->method('getInstancesByInterface')->willReturn([$definition]);
		$settings = $this->createMock(ISettingsStore::class);
		$settings->expects(self::never())->method('remove');
		$storage = $this->createMock(IManagedFileStorageService::class);
		$storage->expects(self::never())->method('delete');

		$this->expectException(LogicException::class);
		(new AgentConfigurationService($settings, $classmap, $storage))->deleteAgent('fixed-agent');
	}
}
