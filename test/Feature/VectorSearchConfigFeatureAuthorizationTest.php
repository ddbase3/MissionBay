<?php declare(strict_types=1);

namespace MissionBay\Test\Feature;

use Base3\Authorization\Api\IFeature;
use Base3\Translation\Api\ITranslation;
use Base3\Usermanager\Permission;
use MissionBay\Feature\VectorSearchConfigFeature;
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
