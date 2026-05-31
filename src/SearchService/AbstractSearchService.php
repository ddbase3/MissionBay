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

namespace MissionBay\SearchService;

use AssistantFoundation\Api\IAiProvider;
use Base3\Api\IClassMap;
use MissionBay\Api\ISearchService;
use RuntimeException;

abstract class AbstractSearchService implements ISearchService {

	/**
	 * @var array<string,mixed>
	 */
	protected array $options = [];

	protected ?IAiProvider $provider = null;

	public function __construct(
		protected readonly IClassMap $classMap
	) {}

	abstract public static function getName(): string;

	abstract protected function getProviderName(): string;

	protected function getDefaultEndpoint(): string {
		return '';
	}

	protected function getDefaultModel(): string {
		return '';
	}

	public function setOptions(array $options): void {
		$this->options = array_merge($this->options, $options);

		if($this->provider instanceof IAiProvider) {
			$this->configureProvider($this->provider);
		}
	}

	public function getOptions(): array {
		return $this->options;
	}

	protected function getProvider(): IAiProvider {
		if($this->provider instanceof IAiProvider) {
			return $this->provider;
		}

		$provider = $this->classMap->getInstanceByInterfaceName(IAiProvider::class, $this->getProviderName());

		if(!$provider instanceof IAiProvider) {
			throw new RuntimeException(
				'Unable to resolve provider "' . $this->getProviderName() . '" for interface ' . IAiProvider::class . '.'
			);
		}

		$this->configureProvider($provider);
		$this->provider = $provider;

		return $this->provider;
	}

	protected function configureProvider(IAiProvider $provider): void {
		$provider->setOptions([
			'endpoint' => $this->getEndpoint($this->options),
			'apikey' => $this->getApiKey($this->options),
			'timeout' => $this->getIntOption($this->options, 'timeout_seconds', 120),
			'connect_timeout' => $this->getIntOption($this->options, 'connect_timeout_seconds', 15)
		]);
	}

	/**
	 * @param array<string,mixed> $runtimeOptions
	 */
	protected function getModel(array $runtimeOptions): string {
		$model = trim((string)($runtimeOptions['model'] ?? ''));

		if($model !== '') {
			return $model;
		}

		return $this->getDefaultModel();
	}

	/**
	 * @param array<string,mixed> $runtimeOptions
	 */
	protected function getEndpoint(array $runtimeOptions): string {
		$endpoint = trim((string)($runtimeOptions['endpoint'] ?? ''));

		if($endpoint !== '') {
			return $endpoint;
		}

		return $this->getDefaultEndpoint();
	}

	/**
	 * @param array<string,mixed> $runtimeOptions
	 */
	protected function getApiKey(array $runtimeOptions): string {
		$apiKey = trim((string)($runtimeOptions['apikey'] ?? ''));

		if($apiKey === '') {
			throw new RuntimeException('Missing API key for search service.');
		}

		return $apiKey;
	}

	/**
	 * @param array<string,mixed> $runtimeOptions
	 */
	protected function getIntOption(array $runtimeOptions, string $key, int $default): int {
		$value = $runtimeOptions[$key] ?? null;

		if($value === null || $value === '' || !is_numeric($value)) {
			return $default;
		}

		$value = (int)$value;

		return $value > 0 ? $value : $default;
	}

	/**
	 * @param array<string,mixed> $runtimeOptions
	 */
	protected function getBoolOption(array $runtimeOptions, string $key, bool $default): bool {
		if(!array_key_exists($key, $runtimeOptions)) {
			return $default;
		}

		$value = $runtimeOptions[$key];

		if(is_bool($value)) {
			return $value;
		}

		if(is_int($value)) {
			return $value !== 0;
		}

		$value = strtolower(trim((string)$value));

		if(in_array($value, ['1', 'true', 'yes', 'on'], true)) {
			return true;
		}

		if(in_array($value, ['0', 'false', 'no', 'off'], true)) {
			return false;
		}

		return $default;
	}

	/**
	 * @param array<string,mixed> $runtimeOptions
	 * @return array<string,mixed>
	 */
	protected function buildRequestOptions(array $runtimeOptions): array {
		return [
			'timeout' => $this->getIntOption($runtimeOptions, 'timeout_seconds', 120),
			'connect_timeout' => $this->getIntOption($runtimeOptions, 'connect_timeout_seconds', 15)
		];
	}

	/**
	 * @param array<string,mixed> $runtimeOptions
	 * @return array<int,string>
	 */
	protected function getStringListOption(array $runtimeOptions, string $key): array {
		$value = $runtimeOptions[$key] ?? [];

		if(is_string($value)) {
			$value = preg_split('/[\r\n,]+/', $value) ?: [];
		}

		if(!is_array($value)) {
			return [];
		}

		$out = [];

		foreach($value as $item) {
			$item = trim((string)$item);

			if($item === '') {
				continue;
			}

			$out[] = $item;
		}

		return array_values(array_unique($out));
	}

	/**
	 * @param array<string,mixed> $value
	 * @return array<int,string>
	 */
	protected function collectStringsRecursively(mixed $value): array {
		$out = [];

		$this->collectStrings($value, $out);

		return $out;
	}

	/**
	 * @param array<int,string> $out
	 */
	private function collectStrings(mixed $value, array &$out): void {
		if(is_string($value)) {
			$out[] = $value;
			return;
		}

		if(!is_array($value)) {
			return;
		}

		foreach($value as $child) {
			$this->collectStrings($child, $out);
		}
	}

	/**
	 * @return array<int,array<string,string>>
	 */
	protected function collectUrlItemsRecursively(mixed $value): array {
		$out = [];

		$this->collectUrlItems($value, $out);

		return $out;
	}

	/**
	 * @param array<int,array<string,string>> $out
	 */
	private function collectUrlItems(mixed $value, array &$out): void {
		if(!is_array($value)) {
			return;
		}

		if(isset($value['url']) && is_string($value['url']) && trim($value['url']) !== '') {
			$out[] = [
				'title' => is_string($value['title'] ?? null) ? $value['title'] : (is_string($value['name'] ?? null) ? $value['name'] : ''),
				'url' => $value['url'],
				'snippet' => is_string($value['snippet'] ?? null) ? $value['snippet'] : (is_string($value['text'] ?? null) ? $value['text'] : ''),
				'source' => is_string($value['source'] ?? null) ? $value['source'] : ''
			];
		}

		foreach($value as $child) {
			$this->collectUrlItems($child, $out);
		}
	}
}
