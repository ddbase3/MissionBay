<?php declare(strict_types=1);

namespace MissionBay\Test\ImageModel;

use AssistantFoundation\Api\IAiProvider;
use AssistantFoundation\Event\AiProviderRequestCompletedEvent;
use Base3\Api\IClassMap;
use Base3\Event\EventManager;
use MissionBay\Ai\AiProviderRequestEventDispatcher;
use MissionBay\ImageModel\MistralImageModel;
use MissionBay\ImageModel\OpenAiImageModel;
use PHPUnit\Framework\TestCase;

final class RecordingImageProvider implements IAiProvider {

	/**
	 * @var array<string,mixed>
	 */
	private array $options = [];

	public string $path = '';

	/**
	 * @var array<string,mixed>
	 */
	public array $payload = [];

	/**
	 * @var array<string,mixed>
	 */
	public array $requestOptions = [];

	/**
	 * @param array<string,mixed> $response
	 */
	public function __construct(
		private readonly array $response
	) {}

	public static function getName(): string {
		return 'recordingimageprovider';
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
		$this->requestOptions = $options;
		return $this->response;
	}

	public function stream(string $path, array $payload, callable $onChunk, array $options = []): void {
	}
}
