<?php declare(strict_types=1);

namespace MissionBay\Resource;

use AssistantFoundation\Api\IAiEmbeddingModel;
use MissionBay\Api\IAgentConfigValueResolver;

class DummyEmbeddingModelAgentResource extends AbstractAgentResource implements IAiEmbeddingModel {

	protected IAgentConfigValueResolver $resolver;

	protected int $dimension = 128;
	protected array $resolvedOptions = [];

	public static function getName(): string {
		return 'dummyembeddingmodelagentresource';
	}

	public function __construct(IAgentConfigValueResolver $resolver, ?string $id = null) {
		parent::__construct($id);
		$this->resolver = $resolver;
	}

	public function getDescription(): string {
		return 'Returns deterministic zero-vectors for testing the embedding pipeline.';
	}

	public function setConfig(array $config): void {
		parent::setConfig($config);

		$value = $this->resolver->resolveValue($config['dimension'] ?? 128);

		$this->dimension = (int)$value;

		$this->resolvedOptions = [
			'dimension' => $this->dimension
		];
	}

	public function getOptions(): array {
		return $this->resolvedOptions;
	}

	public function setOptions(array $options): void {
		$this->resolvedOptions = array_merge($this->resolvedOptions, $options);

		if (isset($options['dimension'])) {
			$this->dimension = (int)$options['dimension'];
		}
	}

	public function embed(array $texts): array {
		if (empty($texts)) {
			return [];
		}

		$vector = array_fill(0, $this->dimension, 0.0);

		$out = [];
		foreach ($texts as $t) {
			$out[] = $vector;
		}

		return $out;
	}
}
