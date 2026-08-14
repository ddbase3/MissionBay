<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 **********************************************************************/

namespace MissionBay\Api;

/**
 * Expands stored component presets into flow resource definitions.
 */
interface IAgentComponentPresetFlowExpander {

	/**
	 * @param array<string,mixed> $flow
	 * @param array<int,string> $presetIds
	 * @return array{
	 *     flow:array<string,mixed>,
	 *     resource_ids:array<string,string>,
	 *     warnings:array<int,string>
	 * }
	 */
	public function expand(array $flow, array $presetIds): array;
}
