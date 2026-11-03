<?php declare(strict_types=1);

namespace MissionBay\Test\Tool\Profile;

final class BatchToolSetSmokeDouble2 implements \MissionBay\Api\IAgentConfigValueResolver  {
		public function resolveValue(array|string|int|float|bool|null $config): mixed { return $config; }
	}
