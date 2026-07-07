<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 **********************************************************************/

namespace MissionBay\Mcp;

/**
 * McpToolResultMapper
 *
 * Converts MissionBay tool return values to MCP tool call results.
 */
class McpToolResultMapper {

	public static function getName(): string {
		return 'mcptoolresultmapper';
	}

	/**
	 * @return array<string,mixed>
	 */
	public function success(mixed $result): array {
		$response = [
			'content' => [
				[
					'type' => 'text',
					'text' => $this->stringify($result)
				]
			]
		];

		$structuredContent = $this->structuredContent($result);

		if($structuredContent !== []) {
			$response['structuredContent'] = $structuredContent;
		}

		return $response;
	}

	/**
	 * @return array<string,mixed>
	 */
	public function error(string $message): array {
		return [
			'isError' => true,
			'content' => [
				[
					'type' => 'text',
					'text' => $message
				]
			]
		];
	}

	/**
	 * @return array<string,mixed>
	 */
	private function structuredContent(mixed $value): array {
		if(is_array($value)) {
			if($this->isList($value)) {
				return [];
			}

			return $value;
		}

		if($value instanceof \JsonSerializable) {
			$json = $value->jsonSerialize();

			if(is_array($json) && !$this->isList($json)) {
				return $json;
			}
		}

		if($value instanceof \stdClass) {
			return (array)$value;
		}

		return [];
	}

	private function stringify(mixed $value): string {
		if(is_string($value)) {
			return $value;
		}

		if($value === null) {
			return 'null';
		}

		if(is_bool($value)) {
			return $value ? 'true' : 'false';
		}

		if(is_int($value) || is_float($value)) {
			return (string)$value;
		}

		$json = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

		if(is_string($json)) {
			return $json;
		}

		return (string)print_r($value, true);
	}

	/**
	 * @param array<mixed> $value
	 */
	private function isList(array $value): bool {
		if(function_exists('array_is_list')) {
			return array_is_list($value);
		}

		$expected = 0;

		foreach($value as $key => $_) {
			if($key !== $expected) {
				return false;
			}

			$expected++;
		}

		return true;
	}
}
