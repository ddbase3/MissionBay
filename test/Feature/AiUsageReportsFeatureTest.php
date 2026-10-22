<?php declare(strict_types=1);

namespace MissionBay\Test\Feature;

use Base3\Translation\Api\ITranslation;
use MissionBay\Feature\AiUsageReportsFeature;
use PHPUnit\Framework\TestCase;

final class AiUsageReportsFeatureTest extends TestCase {

	public function testPermissionAndDefaultAudience(): void {
		$translation = $this->createMock(ITranslation::class);
		$translation->method('translate')->willReturnCallback(
			static fn(string $set, string $section, string $key): string => $section . '.' . $key
		);
		$feature = new AiUsageReportsFeature($translation);
		self::assertSame('aiusagereportsfeature', $feature::getName());
		self::assertSame('aiusagereportsfeature.label', $feature->getLabel());
		self::assertSame('aiusagereportsfeature.description', $feature->getDescription());
		$permissions = $feature->getPermissions();
		self::assertCount(1, $permissions);
		self::assertSame('feature', $permissions[0]->scope);
		self::assertSame($feature::getName(), $permissions[0]->permission);
		self::assertSame(['group' => 'development'], $feature->getDefaultAudience());
	}
}
