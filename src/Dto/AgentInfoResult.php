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

namespace MissionBay\Dto;

/**
 * AgentInfoResult
 *
 * Compact result DTO for read-only information responses.
 *
 * Providers should return focused, context-friendly data:
 * - items for candidate lists
 * - detail for one focused target
 * - links for relevant administration links
 * - errors for structured failures
 */
class AgentInfoResult {

	public bool $success = true;

	public string $topic = '';

	public string $scope = '';

	public string $message = '';

	public array $links = [];

	public array $items = [];

	public array $detail = [];

	public array $errors = [];

	public array $suggestions = [];

	public array $paging = [];

	public function __construct(
		bool $success = true,
		string $topic = '',
		string $scope = '',
		string $message = '',
		array $links = [],
		array $items = [],
		array $detail = [],
		array $errors = [],
		array $suggestions = [],
		array $paging = []
	) {
		$this->success = $success;
		$this->topic = $topic;
		$this->scope = $scope;
		$this->message = $message;
		$this->links = $links;
		$this->items = $items;
		$this->detail = $detail;
		$this->errors = $errors;
		$this->suggestions = $suggestions;
		$this->paging = $paging;
	}

	public static function createSuccess(
		string $topic,
		string $scope,
		string $message = '',
		array $items = [],
		array $detail = [],
		array $links = [],
		array $paging = []
	): self {
		return new self(
			success: true,
			topic: $topic,
			scope: $scope,
			message: $message,
			links: $links,
			items: $items,
			detail: $detail,
			errors: [],
			suggestions: [],
			paging: $paging
		);
	}

	public static function createError(
		string $topic,
		string $scope,
		string $code,
		string $message,
		array $suggestions = []
	): self {
		return new self(
			success: false,
			topic: $topic,
			scope: $scope,
			message: $message,
			links: [],
			items: [],
			detail: [],
			errors: [[
				'code' => $code,
				'message' => $message
			]],
			suggestions: $suggestions,
			paging: []
		);
	}

	public function addError(string $code, string $message, array $context = []): void {
		$error = [
			'code' => $code,
			'message' => $message
		];

		if ($context !== []) {
			$error['context'] = $context;
		}

		$this->errors[] = $error;
		$this->success = false;
	}

	public function toArray(bool $includeEmpty = false): array {
		$out = [
			'success' => $this->success,
			'topic' => $this->topic,
			'scope' => $this->scope
		];

		if ($includeEmpty || $this->message !== '') {
			$out['message'] = $this->message;
		}

		if ($includeEmpty || $this->links !== []) {
			$out['links'] = $this->links;
		}

		if ($includeEmpty || $this->items !== []) {
			$out['items'] = $this->items;
		}

		if ($includeEmpty || $this->detail !== []) {
			$out['detail'] = $this->detail;
		}

		if ($includeEmpty || $this->errors !== []) {
			$out['errors'] = $this->errors;
		}

		if ($includeEmpty || $this->suggestions !== []) {
			$out['suggestions'] = $this->suggestions;
		}

		if ($includeEmpty || $this->paging !== []) {
			$out['paging'] = $this->paging;
		}

		return $out;
	}
}
