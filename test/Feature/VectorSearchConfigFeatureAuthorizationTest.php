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

use Base3\CoreFoundation\Authorization\Api\IFeature;
use Base3\CoreFoundation\Translation\Api\ITranslation;
use Base3\CoreFoundation\Usermanager\Permission;
use Base3\MissionBay\Feature\VectorSearchConfigFeature;
use PHPUnit\Framework\TestCase;

final class VectorSearchConfigFeatureAuthorizationTest extends TestCase {

	public function testFeatureNameAndAudienceAreStable(): void {
		$feature = new VectorSearchConfigFeature($this->createStub(ITranslation::class));
		$this->assertInstanceOf(IFeature::class, $feature);
		$this->assertSame('vectorsearchconfigfeature', VectorSearchConfigFeature::getName());
		$this->assertSame(['group' => 'administration'], $feature->getDefaultAudience());
	}

	public function testFeaturePermissionUsesCanonicalPermissionDescriptor(): void {
		$feature = new VectorSearchConfigFeature($this->createStub(ITranslation::class));
		$this->assertEquals([Permission::for('feature', VectorSearchConfigFeature::getName())], $feature->getPermissions());
	}

	public function testLabelIsTranslatedInOwningPluginDomain(): void {
		$translation = $this->createMock(ITranslation::class);
		$translation->expects($this->once())->method('translate')->with('Features', VectorSearchConfigFeature::getName(), 'label')->willReturn('Vector search configuration');
		$this->assertSame('Vector search configuration', (new VectorSearchConfigFeature($translation))->getLabel());
	}

	public function testDescriptionIsTranslatedInsteadOfHardcoded(): void {
		$translation = $this->createMock(ITranslation::class);
		$translation->expects($this->once())->method('translate')->with('Features', VectorSearchConfigFeature::getName(), 'description')->willReturn('Manage vector search.');
		$this->assertSame('Manage vector search.', (new VectorSearchConfigFeature($translation))->getDescription());
	}
}
