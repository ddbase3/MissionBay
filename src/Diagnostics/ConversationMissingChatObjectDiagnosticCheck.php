<?php declare(strict_types=1);

namespace MissionBay\Diagnostics;

use Base3\Database\Api\IDatabase;
use Base3\Diagnostics\Api\IDiagnosticCheck;
use Base3\Diagnostics\Dto\DiagnosticCheckResult;
use Base3\Diagnostics\Scope\PrivacyDiagnosticScope;
use Base3\Settings\Api\ISettingsStore;

/** System-wide comparison of persisted chatbot conversations with active settings objects. */
final class ConversationMissingChatObjectDiagnosticCheck implements IDiagnosticCheck {

	public function __construct(
		private readonly IDatabase $database,
		private readonly ISettingsStore $settingsStore
	) {}

	public static function getName(): string {
		return 'conversationmissingchatobjectdiagnosticcheck';
	}

	public function getScope(): string {
		return PrivacyDiagnosticScope::getName();
	}

	public function run(): DiagnosticCheckResult {
		$inventory = new ConversationDiagnosticInventory($this->database);
		if (!$inventory->available()) {
			return new DiagnosticCheckResult(DiagnosticCheckResult::NOT_APPLICABLE, 'not_applicable');
		}

		$verified = 0;
		$missing = 0;
		$unknown = 0;
		$orphanConversations = 0;
		$orphanMessages = 0;
		foreach ($inventory->chatbotObjects() as $object) {
			if (!is_array($object)) {
				throw new \RuntimeException('Invalid chatbot diagnostic reference row.');
			}
			$group = $object['chatbot_config_group'] ?? null;
			$name = $object['chatbot_config_name'] ?? null;
			if (!is_string($group) || $group === '' || !is_string($name) || $name === '') {
				$unknown++;
				continue;
			}
			if ($this->settingsStore->has($group, $name)) {
				$verified++;
			}
			else {
				$missing++;
				$orphanConversations += (int)($object['conversations'] ?? 0);
				$orphanMessages += (int)($object['messages'] ?? 0);
			}
		}
		$status = $missing > 0 ? DiagnosticCheckResult::FAILED
			: ($unknown > 0 ? DiagnosticCheckResult::WARNING : DiagnosticCheckResult::PASSED);
		return new DiagnosticCheckResult(
			$status,
			$status,
			['verified' => $verified, 'missing' => $missing, 'unknown' => $unknown],
			$status !== DiagnosticCheckResult::PASSED ? 'remediation' : '',
			[
				'verifiedChatObjects' => $verified,
				'missingChatObjects' => $missing,
				'unresolvedChatObjects' => $unknown,
				'orphanConversations' => $orphanConversations,
				'orphanMessages' => $orphanMessages
			]
		);
	}
}
