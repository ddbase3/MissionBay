<?php declare(strict_types=1);

namespace MissionBay\Resource\AgentTool\CurrencyConvert;

final class FakeHttpState {

	public static ?string $lastUrl = null;
	public static mixed $nextResponse = null; // string|false|null

	public static function reset(): void {
		self::$lastUrl = null;
		self::$nextResponse = null;
	}
}
