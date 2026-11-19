<?php declare(strict_types=1);

namespace Base3\MissionBay\Test\Display\Fixture;

use Base3\Core\Request;

/** In-memory HTTP request for the existing AgentAdmin JSON endpoint. */
final class AgentAdminHitlRequest extends Request {

	/** @var array<string,mixed> */
	public array $body = [];

	public function getJsonBody(): array {
		return $this->body;
	}
}
