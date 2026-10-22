<?php declare(strict_types=1);

namespace MissionBay\Diagnostics;

use Base3\Database\Api\IDatabase;
use Base3\Diagnostics\Api\IDiagnosticCheck;
use Base3\Diagnostics\Dto\DiagnosticCheckResult;
use Base3\Diagnostics\Scope\PrivacyDiagnosticScope;
use Base3\Usermanager\Api\IUsermanager;

/** System-wide, non-disclosing check of persisted conversations against native user lookup. */
final class ConversationMissingUserDiagnosticCheck implements IDiagnosticCheck {

	public function __construct(
		private readonly IDatabase $database,
		private readonly IUsermanager $usermanager
	) {}

	public static function getName(): string {
		return 'conversationmissinguserdiagnosticcheck';
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
		$unresolved = 0;
		$suspectConversations = 0;
		$suspectMessages = 0;
		foreach ($inventory->owners() as $owner) {
			if (!is_array($owner)) {
				throw new \RuntimeException('Invalid conversation diagnostic owner row.');
			}
			$id = $owner['owner_id'] ?? null;
			if (!is_string($id) && !is_int($id)) {
				throw new \RuntimeException('Conversation diagnostic owner ID is missing.');
			}
			$user = $this->usermanager->getUserById($id);
			if ($user !== null && (string)$user->id === (string)$id) {
				$verified++;
			}
			else {
				// IUsermanager explicitly permits null for *absent or unsupported*.
				// This is a suspected orphan, not proof of an actually deleted user.
				$unresolved++;
				$suspectConversations += (int)($owner['conversations'] ?? 0);
				$suspectMessages += (int)($owner['messages'] ?? 0);
			}
		}
		$legacy = $inventory->unresolvedOwners();
		$status = ($unresolved > 0 || $legacy > 0) ? DiagnosticCheckResult::WARNING : DiagnosticCheckResult::PASSED;
		return new DiagnosticCheckResult(
			$status,
			$status === DiagnosticCheckResult::PASSED ? 'passed' : 'warning',
			['verified' => $verified, 'unresolved' => $unresolved, 'legacy' => $legacy],
			$status === DiagnosticCheckResult::WARNING ? 'remediation' : '',
			[
				'verifiedOwners' => $verified,
				'unresolvedOwners' => $unresolved,
				'suspectConversations' => $suspectConversations,
				'suspectMessages' => $suspectMessages,
				'legacyConversations' => $legacy
			]
		);
	}
}
