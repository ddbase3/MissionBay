<?php declare(strict_types=1);

namespace MissionBay\Test\Agent;

use MissionBay\Agent\GlobalAgentKnowledgeService;

final class GlobalAgentKnowledgeRankProbe extends GlobalAgentKnowledgeService {

	public function shouldReplace(array $existing, array $candidate): bool {
		return $this->shouldOverrideMergedEntry($existing, $candidate);
	}

	public function canReadGlobal(array $entry): bool {
		return $this->canAccessEntry($entry);
	}
}
