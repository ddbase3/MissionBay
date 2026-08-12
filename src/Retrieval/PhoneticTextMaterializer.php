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

namespace MissionBay\Retrieval;

use AssistantFoundation\Api\IPhoneticEncoder;
use AssistantFoundation\Api\IRetrievalCollectionDefinition;
use Base3\Api\IClassMap;

/**
 * Builds a deterministic, ordered stream of namespaced phonetic tokens.
 */
final class PhoneticTextMaterializer {

	private const STOPWORDS = [
		'the', 'and', 'for', 'from', 'with', 'without', 'into', 'this', 'that', 'these', 'those',
		'are', 'was', 'were', 'been', 'being',
		'aber', 'als', 'auch', 'auf', 'aus', 'bei', 'das', 'dass', 'dem', 'den', 'der', 'des',
		'die', 'dies', 'diese', 'diesem', 'diesen', 'dieser', 'dieses', 'durch', 'ein', 'eine',
		'einem', 'einen', 'einer', 'eines', 'fuer', 'für', 'gegen', 'mit', 'nach', 'ohne', 'ueber',
		'über', 'und', 'oder', 'unter', 'vom', 'von', 'vor', 'waren', 'weil', 'wenn', 'zum', 'zur'
	];

	public function __construct(
		private readonly IClassMap $classMap,
		private readonly IRetrievalCollectionDefinition $collectionDefinition
	) {}

	/** @param array<string,mixed> $context */
	public function materialize(string $collectionKey, string $text, array $context = []): string {
		$encoderNames = $this->collectionDefinition->getPhoneticEncoderNames($collectionKey, $context);
		if($encoderNames === []) {
			return '';
		}

		$encoders = $this->resolveEncoders($encoderNames);
		$tokens = $this->tokenize($text);
		$out = [];

		foreach($tokens as $token) {
			foreach($encoders as $encoder) {
				$code = trim($encoder->encode($token));
				if($code === '') {
					continue;
				}

				$out[] = $this->namespaceCode($encoder, $code);
			}
		}

		return implode(' ', $out);
	}

	/**
	 * @param string[] $encoderNames
	 * @return IPhoneticEncoder[]
	 */
	private function resolveEncoders(array $encoderNames): array {
		$out = [];

		foreach($encoderNames as $name) {
			$name = trim((string)$name);
			if($name === '') {
				continue;
			}

			$encoder = $this->classMap->getInstanceByInterfaceName(IPhoneticEncoder::class, $name);
			if(!$encoder instanceof IPhoneticEncoder) {
				throw new \RuntimeException("Unable to resolve phonetic encoder '{$name}'.");
			}

			$out[] = $encoder;
		}

		return $out;
	}

	/**
	 * @return string[]
	 */
	private function tokenize(string $text): array {
		$text = trim($text);
		if($text === '') {
			return [];
		}

		$text = preg_replace('~https?://\S+|www\.\S+|[\p{L}\p{N}._%+\-]+@[\p{L}\p{N}.\-]+\.[\p{L}]{2,}~iu', ' ', $text) ?? $text;
		$matches = [];
		preg_match_all('/\p{L}[\p{L}\p{M}\'’\-]*/u', $text, $matches);
		$tokens = $matches[0] ?? [];
		$out = [];
		$stopwords = array_fill_keys(self::STOPWORDS, true);

		foreach($tokens as $token) {
			$token = trim((string)$token, " \t\n\r\0\x0B-'’");
			if($token === '' || mb_strlen($token) < 3) {
				continue;
			}

			$normalized = mb_strtolower($token);
			if(isset($stopwords[$normalized])) {
				continue;
			}

			$out[] = $token;
		}

		return $out;
	}

	private function namespaceCode(IPhoneticEncoder $encoder, string $code): string {
		$algorithm = strtolower(preg_replace('/[^a-z0-9]+/i', '', $encoder->getAlgorithm()) ?? '');
		$version = strtolower(preg_replace('/[^a-z0-9]+/i', '', $encoder->getVersion()) ?? '');
		$code = strtolower(preg_replace('/[^a-z0-9]+/i', '', $code) ?? '');

		if($algorithm === '' || $version === '' || $code === '') {
			throw new \RuntimeException('Phonetic encoder returned an invalid namespace component.');
		}

		return 'ph' . $algorithm . $version . 'x' . $code;
	}
}
