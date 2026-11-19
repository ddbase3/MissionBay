<?php declare(strict_types=1);

namespace Base3\MissionBay\Test\Display\Fixture;

use Base3\CoreFoundation\Authorization\Api\IAuthorizationService;
use Base3\CoreFoundation\Authorization\Dto\AuthorizationDecision;
use Base3\CoreFoundation\Authorization\Dto\AuthorizationRequest;

final class AgentAdminHitlAuthorization implements IAuthorizationService {

	public ?AuthorizationDecision $decision = null;
	/** @var array<int,AuthorizationRequest> */
	public array $requests = [];

	public function getDefaultAuthority(): string {
		return 'test-xrm';
	}

	public function decide(AuthorizationRequest $request): AuthorizationDecision {
		$this->requests[] = $request;
		if ($this->decision === null) {
			throw new \LogicException('Explicit authorization decision required for public AgentAdmin requests.');
		}
		return $this->decision;
	}

	public function decideMany(array $requests): array {
		throw new \LogicException('AgentAdmin requires a single authoritative feature decision.');
	}
}
