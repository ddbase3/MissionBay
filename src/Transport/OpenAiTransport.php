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

namespace MissionBay\Transport;

use AssistantFoundation\Api\IAiFileProvider;
use AssistantFoundation\Dto\AiFileResource;
use AssistantFoundation\Exception\AiProviderRequestException;

class OpenAiTransport extends OpenAiCompatibleTransport implements IAiFileProvider {

	public static function getName(): string {
		return 'openaitransport';
	}

	public function uploadFile(
		string $path,
		AiFileResource $file,
		array $fields = [],
		array $options = []
	): array {
		$url = $this->buildUrl($path);
		$headers = $this->buildHeaders($options, false);
		$timeout = $this->resolveTimeout($options);
		$connectTimeout = $this->resolveConnectTimeout($options);
		$postFields = [];

		foreach($fields as $key => $value) {
			if(!is_scalar($value)) {
				throw new \InvalidArgumentException('OpenAI multipart field must be scalar: ' . (string)$key);
			}
			$postFields[(string)$key] = (string)$value;
		}

		$tempPath = tempnam(sys_get_temp_dir(), 'base3_openai_');
		if(!is_string($tempPath) || $tempPath === '') {
			throw new \RuntimeException('Could not create temporary file for OpenAI upload.');
		}

		try {
			if(file_put_contents($tempPath, $file->getContent()) === false) {
				throw new \RuntimeException('Could not write temporary file for OpenAI upload.');
			}

			$postFields['file'] = new \CURLFile(
				$tempPath,
				$file->getMimeType(),
				$file->getName()
			);

			$ch = curl_init($url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $connectTimeout);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);

			$result = curl_exec($ch);

			if($result === false) {
				$error = curl_error($ch);
				curl_close($ch);
				throw new \RuntimeException('OpenAI file upload failed: ' . $error);
			}

			$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
			curl_close($ch);
		}
		finally {
			if(is_file($tempPath)) {
				unlink($tempPath);
			}
		}

		if($httpCode < 200 || $httpCode >= 300) {
			throw new AiProviderRequestException(
				'OpenAI file upload failed with status ' . $httpCode . ': ' . (string)$result,
				$httpCode,
				(string)$result
			);
		}

		$data = json_decode((string)$result, true);
		if(!is_array($data)) {
			throw new \RuntimeException('Invalid JSON response from OpenAI file upload.');
		}

		return $data;
	}
}
