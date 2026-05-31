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

namespace MissionBay\EmbeddingModel;

use AssistantFoundation\Api\IAiEmbeddingModel;
use AssistantFoundation\Api\IAiProvider;
use Base3\Api\IBase;
use Base3\Api\IClassMap;
use RuntimeException;

abstract class AbstractEmbeddingModel implements IAiEmbeddingModel, IBase {

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

	protected function getDefaultEmbeddingPath(): string {
		return '/v1/embeddings';
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

	public function embed(array $texts): array {
		if(empty($texts)) {
			return [];
		}

		$texts = $this->normalizeTexts($texts);
		$batches = array_chunk($texts, $this->getBatchSize());
		$out = [];

		foreach($batches as $batch) {
			$result = $this->getProvider()->request(
				$this->getEmbeddingPath(),
				$this->buildPayload($batch),
				$this->buildRequestOptions()
			);

			$out = array_merge($out, $this->extractEmbeddings($result));
		}

		if($this->shouldNormalizeVectors()) {
			return array_map(fn(array $vector): array => $this->normalizeVector($vector), $out);
		}

		return $out;
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
			'endpoint' => $this->getEndpoint(),
			'apikey' => $this->getApiKey(),
			'timeout' => $this->getIntOption('timeout_seconds', 60),
			'connect_timeout' => $this->getIntOption('connect_timeout_seconds', 15)
		]);
	}

	/**
	 * @param array<int,string> $texts
	 * @return array<string,mixed>
	 */
	protected function buildPayload(array $texts): array {
		$model = $this->getModel();

		if($model === '') {
			throw new RuntimeException('Missing model name for embedding model.');
		}

		$payload = [
			'model' => $model,
			'input' => $texts
		];

		$dimensions = $this->getNullableIntOption('dimensions');

		if($dimensions !== null) {
			$payload['dimensions'] = $dimensions;
		}

		return $payload;
	}

	/**
	 * @return array<string,mixed>
	 */
	protected function buildRequestOptions(): array {
		return [
			'timeout' => $this->getIntOption('timeout_seconds', 60),
			'connect_timeout' => $this->getIntOption('connect_timeout_seconds', 15)
		];
	}

	/**
	 * @param array<int,mixed> $texts
	 * @return array<int,string>
	 */
	protected function normalizeTexts(array $texts): array {
		$out = [];

		foreach($texts as $text) {
			if(!is_scalar($text) && $text !== null) {
				throw new RuntimeException('Embedding input must contain strings only.');
			}

			$out[] = (string)$text;
		}

		return $out;
	}

	/**
	 * @param array<string,mixed> $result
	 * @return array<int,array<int,float>>
	 */
	protected function extractEmbeddings(array $result): array {
		if(!isset($result['data']) || !is_array($result['data'])) {
			throw new RuntimeException('Malformed embedding response.');
		}

		$data = $result['data'];

		usort($data, function($a, $b): int {
			$aIndex = is_array($a) && isset($a['index']) && is_numeric($a['index']) ? (int)$a['index'] : 0;
			$bIndex = is_array($b) && isset($b['index']) && is_numeric($b['index']) ? (int)$b['index'] : 0;

			return $aIndex <=> $bIndex;
		});

		$out = [];

		foreach($data as $item) {
			if(!is_array($item) || !is_array($item['embedding'] ?? null)) {
				throw new RuntimeException('Malformed embedding item in response.');
			}

			$out[] = array_map(
				fn($value): float => is_numeric($value) ? (float)$value : 0.0,
				$item['embedding']
			);
		}

		return $out;
	}

	protected function getModel(): string {
		$model = trim((string)($this->options['model'] ?? ''));

		if($model !== '') {
			return $model;
		}

		return $this->getDefaultModel();
	}

	protected function getEndpoint(): string {
		$endpoint = trim((string)($this->options['endpoint'] ?? ''));

		if($endpoint !== '') {
			return $endpoint;
		}

		return $this->getDefaultEndpoint();
	}

	protected function getApiKey(): string {
		$apiKey = trim((string)($this->options['apikey'] ?? ''));

		if($apiKey === '') {
			throw new RuntimeException('Missing API key for embedding model.');
		}

		return $apiKey;
	}

	protected function getEmbeddingPath(): string {
		$path = trim((string)($this->options['embedding_path'] ?? ''));

		if($path !== '') {
			return $path;
		}

		return $this->getDefaultEmbeddingPath();
	}

	protected function getBatchSize(): int {
		return $this->getIntOption('batch_size', 100);
	}

	protected function shouldNormalizeVectors(): bool {
		return $this->toBool($this->options['normalize_vectors'] ?? false, false);
	}

	protected function getNullableIntOption(string $key): ?int {
		if(!array_key_exists($key, $this->options)) {
			return null;
		}

		$value = $this->options[$key];

		if($value === null || $value === '' || !is_numeric($value)) {
			return null;
		}

		return (int)$value;
	}

	protected function getIntOption(string $key, int $default): int {
		$value = $this->options[$key] ?? null;

		if($value === null || $value === '' || !is_numeric($value)) {
			return $default;
		}

		$value = (int)$value;

		return $value > 0 ? $value : $default;
	}

	/**
	 * @param array<int,float> $vector
	 * @return array<int,float>
	 */
	protected function normalizeVector(array $vector): array {
		$sum = 0.0;

		foreach($vector as $value) {
			$sum += $value * $value;
		}

		$norm = sqrt($sum);

		if($norm <= 0.0) {
			return $vector;
		}

		return array_map(fn(float $value): float => $value / $norm, $vector);
	}

	protected function toBool(mixed $value, bool $default): bool {
		if($value === null || $value === '') {
			return $default;
		}

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
}
