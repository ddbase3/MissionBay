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

namespace Base3\MissionBay\Test\SearchService;

use Base3\AssistantFoundation\Api\IAiProvider;
use Base3\CoreFoundation\Api\IClassMap;
use Base3\Core\Event\EventManager;
use Base3\MissionBay\Ai\AiProviderRequestEventDispatcher;
use Base3\MissionBay\SearchService\MistralWebSearchService;
use PHPUnit\Framework\TestCase;

final class MistralWebSearchTestProvider implements IAiProvider {

	public string $path = '';

	/** @var array<string,mixed> */
	public array $payload = [];

	/** @var array<string,mixed> */
	private array $options = [];

	/**
	 * @param array<string,mixed> $response
	 */
	public function __construct(
		private readonly array $response
	) {}

	public static function getName(): string {
		return 'mistralwebsearchtestprovider';
	}

	public function setOptions(array $options): void {
		$this->options = array_merge($this->options, $options);
	}

	public function getOptions(): array {
		return $this->options;
	}

	public function request(string $path, array $payload, array $options = []): array {
		$this->path = $path;
		$this->payload = $payload;

		return $this->response;
	}

	public function stream(string $path, array $payload, callable $onChunk, array $options = []): void {}
}
