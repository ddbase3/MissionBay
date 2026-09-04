<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 **********************************************************************/

namespace MissionBay\Exception;

final class AgentRunCancelledException extends \RuntimeException {

	public function __construct(string $message = 'Agent run cancelled by user.') {
		parent::__construct($message);
	}
}
