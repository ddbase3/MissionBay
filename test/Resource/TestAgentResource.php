<?php declare(strict_types=1);

namespace MissionBay\Resource\Test;

use PHPUnit\Framework\TestCase;
use MissionBay\Resource\AbstractAgentResource;
use AssistantFoundation\Api\IAgentContext;

class TestAgentResource extends AbstractAgentResource {

	public static function getName(): string {
		return 'testagentresource';
	}

	public function getDescription(): string {
		return 'Test resource description';
	}

}
