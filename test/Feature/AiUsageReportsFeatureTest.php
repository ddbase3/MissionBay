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

namespace Base3\MissionBay\Test\Feature;

use Base3\CoreFoundation\Translation\Api\ITranslation;
use Base3\MissionBay\Feature\AiUsageReportsFeature;
use PHPUnit\Framework\TestCase;

final class AiUsageReportsFeatureTest extends TestCase {

	public function testPermissionAndDefaultAudience(): void {
		$translation = $this->createStub(ITranslation::class);
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
