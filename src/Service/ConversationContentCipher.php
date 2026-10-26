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
use CredentialFoundation\Api\IEncryptionProfileService;

/**
 * Encrypts and decrypts persisted conversation fields using BASE3 crypt methods.
 *
 * KeyHarbor owns encryption profiles, their active write keys and historical
 * key sources. This class only selects the recorded method and applies ICrypt.
 * No key material or plaintext is persisted in metadata or logged here.
 */
final class ConversationContentCipher {

	public function __construct(
		private readonly IEncryptionProfileService $encryption,
		private readonly IClassMap $classmap
	) {}

	/**
	 * Encrypt a field using the method and key ID that will be stored with its row.
	 * A historical row can retain its original method/key after an assignment change.
	 *
	 * @param string $plaintext Plaintext field value
	 * @param string $method Versioned method identifier or none
	 * @param string|null $keyId Persisted key ID, null for unencrypted fields
	 * @return string Ciphertext or original plaintext when the row is unencrypted
	 * @throws \RuntimeException If the method, historical key or crypt operation fails
	 */
	public function encode(string $plaintext, string $method, ?string $keyId): string {
		if ($method === 'none') {
			$this->requireUnencryptedKey($keyId);
			return $plaintext;
		}

		return $this->method($method)->encrypt(
			$plaintext,
			$this->encryption->getReadKey($method, $this->requireKeyId($keyId))
		);
	}

	/**
	 * Decode a persisted field solely from its stored method and key ID.
	 * Current consumer assignments do not influence historical decryption.
	 *
	 * @param string $ciphertext Persisted field value
	 * @param string $method Persisted method or none
	 * @param string|null $keyId Persisted historical key ID
	 * @return string Original plaintext
	 * @throws \RuntimeException If the method, key or authenticated decryption fails
	 */
	public function decode(string $ciphertext, string $method, ?string $keyId): string {
		if ($method === 'none') {
			$this->requireUnencryptedKey($keyId);
			return $ciphertext;
		}

		return $this->method($method)->decrypt(
			$ciphertext,
			$this->encryption->getReadKey($method, $this->requireKeyId($keyId))
		);
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
