<?php declare(strict_types=1);

namespace MissionBay\Test\Display;

use Base3\Authorization\Api\IAuthorizationService;
use Base3\Authorization\Dto\AuthorizationDecision;
use Base3\Authorization\Dto\AuthorizationRequest;
use Base3\Translation\Api\ITranslation;
use MissionBay\Display\AbstractServiceConfigDisplay;
use MissionBay\Display\VectorSearchConfigDisplay;
use MissionBay\Feature\VectorSearchConfigFeature;
use PHPUnit\Framework\TestCase;

final class VectorSearchConfigDisplayConnectionBoundaryTest extends TestCase {

	public function testVectorSearchDisplayDefinesAnIndependentDirectAccessFeature(): void {
		$displayClass = new \ReflectionClass(VectorSearchConfigDisplay::class);
		self::assertTrue($displayClass->isInstantiable());
		$display = $displayClass->newInstanceWithoutConstructor();
		self::assertSame(
			VectorSearchConfigFeature::getName(),
			$displayClass->getMethod('settingsTabFeatureName')->invoke($display)
		);

		$feature = new VectorSearchConfigFeature($this->createMock(ITranslation::class));
		self::assertSame(['group' => 'administration'], $feature->getDefaultAudience());
		$permissions = $feature->getPermissions();
		self::assertCount(1, $permissions);
		self::assertSame('feature', $permissions[0]->scope);
		self::assertSame(VectorSearchConfigFeature::getName(), $permissions[0]->permission);
	}

	public function testDirectHtmlAndJsonRequestsDenyWithoutTheVectorSearchFeature(): void {
		$authorization = $this->createMock(IAuthorizationService::class);
		$authorization->method('getDefaultAuthority')->willReturn('base3');
		$authorization->expects(self::exactly(2))->method('decide')
			->with(self::callback(static function(AuthorizationRequest $request): bool {
				return $request->authority === 'base3'
					&& $request->permission->scope === 'feature'
					&& $request->permission->permission === VectorSearchConfigFeature::getName();
			}))
			->willReturn(AuthorizationDecision::deny());

		$displayClass = new \ReflectionClass(VectorSearchConfigDisplay::class);
		$display = $displayClass->newInstanceWithoutConstructor();
		(new \ReflectionProperty(AbstractServiceConfigDisplay::class, 'settingsAuthorization'))
			->setValue($display, $authorization);

		foreach (['html', 'json'] as $format) {
			http_response_code(200);
			self::assertSame('', $display->getOutput($format, true));
			self::assertSame(403, http_response_code());
		}
	}

	public function testVectorSearchFormReferencesAnExistingConnectionWithoutEditingIt(): void {
		$template = file_get_contents(dirname(__DIR__, 2) . '/tpl/Display/VectorSearchConfigDisplay.php');

		$this->assertIsString($template);
		$this->assertStringContainsString('name="connection"', $template);
		$this->assertStringNotContainsString('name="baseUrl"', $template);
		$this->assertStringNotContainsString('name="authType"', $template);
		$this->assertStringNotContainsString('name="apiKey"', $template);
		$this->assertStringNotContainsString('name="apikey"', $template);
		$this->assertStringNotContainsString('name="secret"', $template);
		$this->assertStringNotContainsString('name="timeoutSeconds"', $template);
		$this->assertStringNotContainsString('name="connectTimeoutSeconds"', $template);
		$this->assertStringNotContainsString('data-role="connectionhint"', $template);
	}
}
