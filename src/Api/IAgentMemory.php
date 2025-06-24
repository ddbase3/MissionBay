<?php declare(strict_types=1);

namespace MissionBay\Api;

use Base3\Api\IBase;

interface IAgentMemory extends IBase
{
	/**
	 * Gibt den Chat-Verlauf für einen bestimmten Node zurück.
	 */
	public function loadNodeHistory(string $nodeId): array;

	/**
	 * Fügt dem Verlauf eines bestimmten Nodes einen Eintrag hinzu.
	 */
	public function appendNodeHistory(string $nodeId, string $role, string $text): void;

	/**
	 * Setzt den Verlauf eines bestimmten Nodes zurück.
	 */
	public function resetNodeHistory(string $nodeId): void;

	/**
	 * Speichert einen beliebigen Key-Wert-Eintrag (globaler Datenbereich).
	 */
	public function put(string $key, mixed $value): void;

	/**
	 * Gibt einen gespeicherten Wert zurück.
	 */
	public function get(string $key): mixed;

	/**
	 * Entfernt einen gespeicherten Eintrag.
	 */
	public function forget(string $key): void;

	/**
	 * Gibt alle gespeicherten Schlüssel zurück.
	 */
	public function keys(): array;
}

