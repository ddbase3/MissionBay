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

namespace MissionBay\Service;

use Base3\Api\IClassMap;
use Base3\Crypt\Api\ICrypt;
use MissionBay\Api\IAgentConfigValueResolver;

/**
 * Selects framework-owned encryption methods for persisted conversation fields.
 * No encryption keys or plaintext content are written to logs or metadata.
 */
final class ConversationContentCipher {

	private string $writeMethod = 'none';
	private ?string $writeKeyId = null;
	/** @var array<string,array|string|int|float|bool|null> */
	private array $keyDefinitions = [];

	public function __construct(
		private readonly IAgentConfigValueResolver $resolver,
		private readonly IClassMap $classmap
	) {}

	/** @param array<string,mixed> $configuration */
	public function setConfig(array $configuration): void {
		$method = trim((string)($configuration['method'] ?? 'none'));
		$this->writeMethod = $method !== '' ? $method : 'none';
		$keyId = trim((string)($configuration['key_id'] ?? ''));
		$this->writeKeyId = $keyId !== '' ? $keyId : null;
		$definitions = $configuration['keys'] ?? [];
		if (!is_array($definitions)) {
			throw new \InvalidArgumentException('Conversation encryption keys must be an ID-to-source map.');
		}
		$this->keyDefinitions = $definitions;

		if ($this->writeMethod !== 'none') {
			if ($this->writeKeyId === null) {
				throw new \InvalidArgumentException('Encrypted conversations require a key ID.');
			}
			$this->method($this->writeMethod);
			$this->key($this->writeKeyId);
		}
	}

	public function getWriteMethod(): string {
		return $this->writeMethod;
	}

	public function getWriteKeyId(): ?string {
		return $this->writeKeyId;
	}

	public function encode(string $plaintext, string $method, ?string $keyId): string {
		if ($method === 'none') {
			$this->requireUnencryptedKey($keyId);
			return $plaintext;
		}

		return $this->method($method)->encrypt($plaintext, $this->key($this->requireKeyId($keyId)));
	}

	public function decode(string $ciphertext, string $method, ?string $keyId): string {
		if ($method === 'none') {
			$this->requireUnencryptedKey($keyId);
			return $ciphertext;
		}

		return $this->method($method)->decrypt($ciphertext, $this->key($this->requireKeyId($keyId)));
	}

	private function method(string $method): ICrypt {
		$matched = null;
		foreach ($this->classmap->getInstancesByInterface(ICrypt::class) as $candidate) {
			if (!$candidate instanceof ICrypt || $candidate->getMethod() !== $method) {
				continue;
			}
			if ($matched !== null) {
				throw new \RuntimeException('Duplicate conversation encryption method: ' . $method);
			}
			$matched = $candidate;
		}
		if ($matched === null) {
			throw new \RuntimeException('Unknown conversation encryption method: ' . $method);
		}

		return $matched;
	}

	private function key(string $keyId): string {
		if (!array_key_exists($keyId, $this->keyDefinitions)) {
			throw new \RuntimeException('Conversation encryption key source is not available: ' . $keyId);
		}
		$definition = $this->keyDefinitions[$keyId];
		if (!is_array($definition) && !is_string($definition) && !is_int($definition)
			&& !is_float($definition) && !is_bool($definition) && $definition !== null) {
			throw new \InvalidArgumentException('Invalid conversation encryption key source definition.');
		}
		$key = $this->resolver->resolveValue($definition);
		if (!is_string($key) || $key === '') {
			throw new \RuntimeException('Conversation encryption key source produced no secret: ' . $keyId);
		}

		return $key;
	}

	private function requireKeyId(?string $keyId): string {
		if (!is_string($keyId) || trim($keyId) === '') {
			throw new \RuntimeException('Encrypted conversation field is missing its key ID.');
		}

		return $keyId;
	}

	private function requireUnencryptedKey(?string $keyId): void {
		if ($keyId !== null && $keyId !== '') {
			throw new \RuntimeException('Unencrypted conversation field unexpectedly references a key.');
		}
	}
}
