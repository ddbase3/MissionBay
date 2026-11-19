<?php declare(strict_types=1);

namespace Base3\MissionBay\Test\Display\Fixture;

use Base3\AssistantFoundation\Api\IAgentConfigFormService;
use Base3\CoreFoundation\Api\IMvcView;

final class AgentAdminHitlConfigForm implements IAgentConfigFormService {
	public function getDefaultSettings(): array { return []; }
	public function normalizeSettings(array $settings): array { return $settings; }
	public function getPostedSettings(array &$errors, ?string $runtimeId = null): array { return []; }
	public function getPostedViewValues(?string $runtimeId = null): array { return []; }
	public function settingsToViewValues(array $settings): array { return $settings; }
	public function assignViewData(IMvcView $view, array $settings, array $options = []): void {}
}
