<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 *
 * MissionBay extends the BASE3 framework with a modular runtime
 * foundation for agent flows, reusable nodes, and dockable resources.
 * It provides declarative execution for AI-driven workflows.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * https://base3.de/v/missionbay
 * https://github.com/ddbase3/MissionBay
 **********************************************************************/

namespace MissionBay\Api;

/**
 * Client contract for the MCP server primitives consumed by MissionBay.
 */
interface IMcpClient {

	/** @return array<string,mixed> */
	public function initialize(): array;

	/** @return array<string,mixed> */
	public function getInitializeResult(): array;

	/** @return array<int,array<string,mixed>> */
	public function listTools(): array;

	/** @param array<string,mixed> $arguments @return array<string,mixed> */
	public function callTool(string $name, array $arguments = []): array;

	/** @return array<int,array<string,mixed>> */
	public function listResources(): array;

	/** @return array<int,array<string,mixed>> */
	public function listResourceTemplates(): array;

	/** @return array<string,mixed> */
	public function readResource(string $uri): array;

	/** @return array<int,array<string,mixed>> */
	public function listPrompts(): array;

	/** @param array<string,mixed> $arguments @return array<string,mixed> */
	public function getPrompt(string $name, array $arguments = []): array;

	public function getProtocolVersion(): string;

	public function getSessionId(): string;
}
