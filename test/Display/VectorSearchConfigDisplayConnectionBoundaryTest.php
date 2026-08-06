<?php declare(strict_types=1);

namespace MissionBay\Test\Display;

use PHPUnit\Framework\TestCase;

final class VectorSearchConfigDisplayConnectionBoundaryTest extends TestCase {

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
