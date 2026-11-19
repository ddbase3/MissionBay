<?php declare(strict_types=1);

namespace Base3\MissionBay\Test\Display\Fixture;

use Base3\Core\Usermanager\No\NoUsermanager;
use Base3\CoreFoundation\Usermanager\User;

final class AgentAdminHitlUsermanager extends NoUsermanager {
	public ?User $actor = null;
	public function getUser() { return $this->actor; }
}
