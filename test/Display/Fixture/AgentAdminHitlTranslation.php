<?php declare(strict_types=1);

namespace Base3\MissionBay\Test\Display\Fixture;

use Base3\CoreFoundation\Translation\Api\ITranslation;

final class AgentAdminHitlTranslation implements ITranslation {
	public function translate(string $set, string $section, string $key, string $fallback = '', array $replacements = []): string { return $key; }
}
