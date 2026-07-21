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

namespace MissionBay\Api;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Dto\AgentAction;
use AssistantFoundation\Dto\AgentActionReview;
use AssistantFoundation\Dto\AgentMutationCommitDecision;
use AssistantFoundation\Dto\AgentMutationCommitSnapshot;

/**
 * IAgentMutationGuardedTool
 *
 * Optional capability contract for tools whose mutating functions require an
 * approval-bound commit guard.
 *
 * The contract covers the complete guarded mutation lifecycle:
 *
 * 1. captureMutationCommitSnapshot() captures the authorization identity and
 *    relevant resource state before the mutation is shown to the user.
 * 2. getActionReview() describes that exact captured state in user-facing
 *    terms. It may resolve technical IDs to names and explain before/after
 *    values, but it must not perform the mutation or any other side effect.
 * 3. validateMutationCommit() rechecks authorization and resource state at the
 *    final execution boundary before callTool() is allowed to write.
 *
 * Tool definitions that require this contract should declare mutation=true,
 * requiresApproval=true and commitGuardRequired=true. Read-only functions in
 * the same IAgentTool do not use these methods.
 *
 * Implementations should build the review from data already captured in the
 * snapshot whenever possible. This guarantees that the user sees the same
 * state that is later protected by optimistic concurrency validation and
 * avoids duplicate lookups. The snapshot metadata may contain domain-specific
 * review data, but public UIs receive only AgentActionReview and AgentAction.
 *
 * Localization is intentionally not part of this interface. A tool that needs
 * localized review text may inject and use its normal language services.
 *
 * Wrappers that expose a guarded tool under another function name must
 * translate the AgentAction back to the original name and delegate all three
 * methods without changing the action id, input or fingerprint.
 */
interface IAgentMutationGuardedTool {

	/**
	 * Captures the server-owned state against which approval and later execution
	 * are bound.
	 *
	 * The method may read authorization data and domain state. It must not mutate
	 * application data. The returned action id and fingerprint must match the
	 * supplied action and action fingerprint exactly.
	 */
	public function captureMutationCommitSnapshot(
		AgentAction $action,
		string $actionFingerprint,
		IAgentContext $context
	): AgentMutationCommitSnapshot;

	/**
	 * Creates the user-facing review for the exact guarded mutation snapshot.
	 *
	 * The title and message should clearly state what will happen. The summary
	 * should contain directly understandable values, preferably with technical
	 * identifiers resolved to names and with relevant current/target states.
	 * Exact raw function names and arguments do not need to be repeated because
	 * they remain available separately as technical details through AgentAction.
	 *
	 * This method must be side-effect free. Failure to create a trustworthy
	 * review is a guarded-mutation error and should prevent approval from being
	 * requested rather than silently falling back to an unrelated description.
	 */
	public function getActionReview(
		AgentAction $action,
		AgentMutationCommitSnapshot $snapshot,
		IAgentContext $context
	): AgentActionReview;

	/**
	 * Rechecks the captured authorization identity and relevant resource state
	 * immediately before the mutation is executed.
	 *
	 * The method must deny execution when the snapshot does not belong to the
	 * action, authorization changed, the protected state became stale, or the
	 * mutation is no longer permitted.
	 */
	public function validateMutationCommit(
		AgentAction $action,
		AgentMutationCommitSnapshot $snapshot,
		IAgentContext $context
	): AgentMutationCommitDecision;
}
