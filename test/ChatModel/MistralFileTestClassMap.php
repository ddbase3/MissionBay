<?php declare(strict_types=1);

namespace MissionBay\Test\ChatModel;

use AssistantFoundation\Api\IAiFileProvider;
use AssistantFoundation\Api\IAiProvider;
use AssistantFoundation\Dto\AiFileResource;
use AssistantFoundation\Dto\AiProviderFileReference;
use AssistantFoundation\Exception\AiProviderRequestException;
use Base3\Api\IClassMap;
use Base3\Event\EventManager;
use MissionBay\Ai\AiProviderRequestEventDispatcher;
use MissionBay\ChatModel\MistralChatModel;
use PHPUnit\Framework\TestCase;

final class MistralFileTestClassMap implements IClassMap {

	/** @var array<int,object> */
	private array $emptyInstances = [];

	private mixed $emptyInstance = null;

	public function __construct(
		private readonly IAiProvider $provider
	) {}

	public function instantiate(string $class) {
		return null;
	}

	public function instantiateWith(string $class, array $arguments = []) {
		return null;
	}

	public function generate($regenerate = false): void {}

	public function getApps() {
		return [];
	}

	public function &getInstances(array $criteria = []) {
		return $this->emptyInstances;
	}

	public function &getInstancesByInterface($interface) {
		return $this->emptyInstances;
	}

	public function &getInstancesByAppInterface($app, $interface, $retry = false) {
		return $this->emptyInstances;
	}

	public function &getInstanceByAppName($app, $name, $retry = false) {
		return $this->emptyInstance;
	}

	public function getClassByInterfaceName(string $interface, string $name): ?string {
		return null;
	}

	public function &getInstanceByInterfaceName($interface, $name, $retry = false) {
		if($interface === IAiProvider::class) {
			$provider = $this->provider;
			return $provider;
		}

		return $this->emptyInstance;
	}

	public function &getInstanceByAppInterfaceName($app, $interface, $name, $retry = false) {
		return $this->getInstanceByInterfaceName($interface, $name, $retry);
	}

	public function getPlugins() {
		return [];
	}
}
