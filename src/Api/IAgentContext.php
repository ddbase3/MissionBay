<?php declare(strict_types=1);

namespace MissionBay\Api;

interface IAgentContext {
	public function getMemory(): IAgentMemory;
	public function setVar(string $key, mixed $value): void;
	public function getVar(string $key): mixed;
}

