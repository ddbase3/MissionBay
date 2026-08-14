<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 **********************************************************************/

namespace MissionBay\Api;

/**
 * Provides typed views of configured agent component presets without materializing them.
 */
interface IAgentComponentPresetCatalog {

	/**
	 * @return array<int,array{id:string,label:string,type:string}>
	 */
	public function getPresetOptionsByInterface(string $interfaceName): array;

	public function presetImplements(string $presetId, string $interfaceName): bool;
}
