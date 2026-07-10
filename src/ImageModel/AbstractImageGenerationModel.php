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

namespace MissionBay\ImageModel;

use AssistantFoundation\Api\IAiProvider;
use AssistantFoundation\Dto\AiImageResult;
use Base3\Api\IClassMap;
use MissionBay\Ai\AiResultNormalizer;
use MissionBay\Api\IImageGenerationModel;
use RuntimeException;

abstract class AbstractImageGenerationModel implements IImageGenerationModel {

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

	protected function getDefaultGenerationPath(): string {
		return '/v1/images/generations';
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

	public function generate(string $prompt, array $options = []): array {
		return $this->generateResult($prompt, $options)->getImages();
	}

	public function generateResult(string $prompt, array $options = []): AiImageResult {
		$startedAt = microtime(true);
		$prompt = trim($prompt);

		if($prompt === '') {
			throw new RuntimeException('Missing image prompt.');
		}

		$runtimeOptions = array_merge($this->options, $options);
		$result = $this->getProvider()->request(
			$this->getGenerationPath($runtimeOptions),
			$this->buildPayload($prompt, $runtimeOptions),
			$this->buildRequestOptions($runtimeOptions)
		);
		$images = $this->extractImages($result, $runtimeOptions);

		return new AiImageResult(
			$images,
			AiResultNormalizer::metadata('image', $result, [
				'provider' => $this->getProviderName(),
				'model' => $this->getModel($runtimeOptions),
				'adapter' => static::getName(),
				'started_at' => $startedAt,
				'usage_metrics' => [
					'input_prompts' => 1,
					'output_images' => count($images)
				]
			], $startedAt),
			$result
		);
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
	 * @return array<string,mixed>
	 */
	protected function buildPayload(string $prompt, array $runtimeOptions): array {
		$model = $this->getModel($runtimeOptions);

		if($model === '') {
			throw new RuntimeException('Missing model name for image generation model.');
		}

		$payload = [
			'model' => $model,
			'prompt' => $prompt
		];

		$this->mapOptionalString($payload, $runtimeOptions, 'size', 'size');
		$this->mapOptionalString($payload, $runtimeOptions, 'quality', 'quality');
		$this->mapOptionalString($payload, $runtimeOptions, 'background', 'background');
		$this->mapOptionalString($payload, $runtimeOptions, 'output_format', 'output_format');
		$this->mapOptionalString($payload, $runtimeOptions, 'moderation', 'moderation');
		$this->mapOptionalInt($payload, $runtimeOptions, 'output_compression', 'output_compression');
		$this->mapOptionalInt($payload, $runtimeOptions, 'n', 'n');

		return $payload;
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
	 * @param array<string,mixed> $result
	 * @param array<string,mixed> $runtimeOptions
	 * @return array<int,array<string,mixed>>
	 */
	protected function extractImages(array $result, array $runtimeOptions): array {
		if(!isset($result['data']) || !is_array($result['data'])) {
			throw new RuntimeException('Malformed image generation response.');
		}

		$format = strtolower(trim((string)($runtimeOptions['output_format'] ?? 'png')));
		$mimeType = match($format) {
			'jpeg', 'jpg' => 'image/jpeg',
			'webp' => 'image/webp',
			default => 'image/png'
		};

		$out = [];

		foreach($result['data'] as $index => $item) {
			if(!is_array($item)) {
				continue;
			}

			$out[] = [
				'index' => is_numeric($index) ? (int)$index : count($out),
				'mime_type' => $mimeType,
				'format' => $format,
				'b64_json' => is_string($item['b64_json'] ?? null) ? $item['b64_json'] : '',
				'url' => is_string($item['url'] ?? null) ? $item['url'] : '',
				'revised_prompt' => is_string($item['revised_prompt'] ?? null) ? $item['revised_prompt'] : ''
			];
		}

		return $out;
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
			throw new RuntimeException('Missing API key for image generation model.');
		}

		return $apiKey;
	}

	/**
	 * @param array<string,mixed> $runtimeOptions
	 */
	protected function getGenerationPath(array $runtimeOptions): string {
		$path = trim((string)($runtimeOptions['generation_path'] ?? ''));

		if($path !== '') {
			return $path;
		}

		return $this->getDefaultGenerationPath();
	}

	/**
	 * @param array<string,mixed> $payload
	 * @param array<string,mixed> $runtimeOptions
	 */
	protected function mapOptionalString(array &$payload, array $runtimeOptions, string $sourceKey, string $targetKey): void {
		$value = trim((string)($runtimeOptions[$sourceKey] ?? ''));

		if($value === '') {
			return;
		}

		$payload[$targetKey] = $value;
	}

	/**
	 * @param array<string,mixed> $payload
	 * @param array<string,mixed> $runtimeOptions
	 */
	protected function mapOptionalInt(array &$payload, array $runtimeOptions, string $sourceKey, string $targetKey): void {
		if(!array_key_exists($sourceKey, $runtimeOptions)) {
			return;
		}

		$value = $runtimeOptions[$sourceKey];

		if($value === null || $value === '' || !is_numeric($value)) {
			return;
		}

		$payload[$targetKey] = (int)$value;
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
}
