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

use MissionBay\Transport\MistralTransport;
use RuntimeException;

class MistralImageModel extends AbstractImageGenerationModel {

	public static function getName(): string {
		return 'mistralimagemodel';
	}

	protected function getProviderName(): string {
		return MistralTransport::getName();
	}

	protected function getDefaultEndpoint(): string {
		return 'https://api.mistral.ai';
	}

	protected function getDefaultModel(): string {
		return 'mistral-small-latest';
	}

	protected function getDefaultGenerationPath(): string {
		return '/v1/chat/completions';
	}

	/**
	 * @param array<string,mixed> $runtimeOptions
	 * @return array<string,mixed>
	 */
	protected function buildPayload(string $prompt, array $runtimeOptions): array {
		$model = $this->getModel($runtimeOptions);

		if($model === '') {
			throw new RuntimeException('Missing model name for Mistral image generation.');
		}

		return [
			'model' => $model,
			'messages' => [
				[
					'role' => 'user',
					'content' => $prompt
				]
			],
			'tools' => [
				[
					'type' => 'image_generation'
				]
			]
		];
	}

	/**
	 * @param array<string,mixed> $result
	 * @param array<string,mixed> $runtimeOptions
	 * @return array<int,array<string,mixed>>
	 */
	protected function extractImages(array $result, array $runtimeOptions): array {
		$choices = $result['choices'] ?? null;

		if(!is_array($choices)) {
			throw new RuntimeException('Malformed Mistral image generation response.');
		}

		$images = [];

		foreach($choices as $choice) {
			if(!is_array($choice)) {
				continue;
			}

			foreach($this->getChoiceMessages($choice) as $message) {
				$content = $message['content'] ?? null;

				if(!is_array($content)) {
					continue;
				}

				foreach($content as $chunk) {
					if(!is_array($chunk) || ($chunk['type'] ?? null) !== 'image_url') {
						continue;
					}

					$url = trim((string)($chunk['image_url'] ?? ''));

					if($url === '') {
						continue;
					}

					$images[] = [
						'index' => count($images),
						'mime_type' => '',
						'format' => '',
						'b64_json' => '',
						'url' => $url,
						'revised_prompt' => ''
					];
				}
			}
		}

		if($images === []) {
			throw new RuntimeException('Mistral image generation response contains no image URL.');
		}

		return $images;
	}

	/**
	 * @param array<string,mixed> $choice
	 * @return array<int,array<string,mixed>>
	 */
	private function getChoiceMessages(array $choice): array {
		$messages = $choice['messages'] ?? null;

		if(is_array($messages)) {
			return array_values(array_filter($messages, 'is_array'));
		}

		$message = $choice['message'] ?? null;

		if(is_array($message)) {
			return [$message];
		}

		return [];
	}
}
