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

namespace MissionBay\Orchestrator\Validation;

/**
 * JsonSchemaValidator
 *
 * Deterministic, dependency-free validation for the JSON Schema subset used by
 * MissionBay tool contracts. It deliberately validates runtime values without
 * coercion and never includes rejected values in issue records.
 */
final class JsonSchemaValidator {

	public function __construct(
		private readonly int $maxIssues = 25,
		private readonly int $maxDepth = 64
	) {}

	/**
	 * @return array{valid:bool,schema_valid:bool,issues:array<int,array<string,mixed>>}
	 */
	public function validate(mixed $value, mixed $schema): array {
		$root = $this->normalizeSchema($schema);
		$issues = $this->validateNode($value, $root, '$', $root, 0, []);
		$issues = array_slice($issues, 0, max(1, $this->maxIssues));
		$schemaValid = true;

		foreach ($issues as $issue) {
			if (str_starts_with((string)($issue['code'] ?? ''), 'schema_')) {
				$schemaValid = false;
				break;
			}
		}

		return [
			'valid' => $issues === [],
			'schema_valid' => $schemaValid,
			'issues' => $issues
		];
	}

	private function normalizeSchema(mixed $schema): mixed {
		if ($schema instanceof \stdClass) {
			$schema = get_object_vars($schema);
		}

		if (!is_array($schema)) {
			return $schema;
		}

		$result = [];
		foreach ($schema as $key => $value) {
			$result[$key] = $this->normalizeSchema($value);
		}

		return $result;
	}

	/**
	 * @param array<int,string> $refStack
	 * @return array<int,array<string,mixed>>
	 */
	private function validateNode(
		mixed $value,
		mixed $schema,
		string $path,
		mixed $rootSchema,
		int $depth,
		array $refStack
	): array {
		if ($depth > $this->maxDepth) {
			return [$this->issue(
				$path,
				'$schema',
				'schema_max_depth_exceeded',
				'Tool schema validation exceeded the configured recursion depth.',
				['max_depth' => $this->maxDepth],
				$value
			)];
		}

		if (is_bool($schema)) {
			return $schema
				? []
				: [$this->issue($path, '$schema', 'false_schema', 'The declared schema rejects every value.', [], $value)];
		}

		if (!is_array($schema)) {
			return [$this->issue(
				$path,
				'$schema',
				'schema_invalid_node',
				'Schema nodes must be objects or booleans.',
				['schema_type' => get_debug_type($schema)],
				$value
			)];
		}

		$issues = [];

		if (array_key_exists('$ref', $schema)) {
			$reference = $schema['$ref'];
			if (!is_string($reference) || trim($reference) === '') {
				return [$this->issue($path, '$ref', 'schema_invalid_ref', 'Schema reference must be a non-empty string.', [], $value)];
			}

			if (in_array($reference, $refStack, true)) {
				return [$this->issue(
					$path,
					'$ref',
					'schema_circular_ref',
					'Circular local schema reference detected.',
					['reference' => $reference],
					$value
				)];
			}

			$resolved = $this->resolveLocalReference($rootSchema, $reference);
			if (!$resolved['found']) {
				return [$this->issue(
					$path,
					'$ref',
					'schema_unresolved_ref',
					'Only resolvable local JSON Pointer references are supported.',
					['reference' => $reference],
					$value
				)];
			}

			$issues = array_merge($issues, $this->validateNode(
				$value,
				$resolved['schema'],
				$path,
				$rootSchema,
				$depth + 1,
				array_merge($refStack, [$reference])
			));
		}

		$issues = array_merge($issues, $this->validateComposition($value, $schema, $path, $rootSchema, $depth, $refStack));
		if ($this->hasSchemaIssue($issues)) {
			return $issues;
		}

		if (array_key_exists('const', $schema) && !$this->jsonEquals($value, $schema['const'])) {
			$issues[] = $this->issue($path, 'const', 'const_mismatch', 'Value does not match the declared constant.', [], $value);
		}

		if (array_key_exists('enum', $schema)) {
			if (!is_array($schema['enum'])) {
				$issues[] = $this->issue($path, 'enum', 'schema_invalid_enum', 'Schema enum must be an array.', [], $value);
			} elseif (!$this->matchesAnyEnumValue($value, $schema['enum'])) {
				$issues[] = $this->issue(
					$path,
					'enum',
					'enum_mismatch',
					'Value is not one of the values allowed by the tool contract.',
					['allowed_count' => count($schema['enum'])],
					$value
				);
			}
		}

		$typeResult = $this->validateType($value, $schema, $path);
		$issues = array_merge($issues, $typeResult['issues']);
		if (!$typeResult['matches']) {
			return $issues;
		}

		$objectValues = $this->toObjectProperties($value);
		if ($objectValues !== null) {
			$issues = array_merge($issues, $this->validateObject(
				$objectValues,
				$schema,
				$path,
				$rootSchema,
				$depth,
				$refStack
			));
		}

		if ($this->isArrayValue($value)) {
			$issues = array_merge($issues, $this->validateArray(
				array_values($value),
				$schema,
				$path,
				$rootSchema,
				$depth,
				$refStack
			));
		}

		if (is_string($value)) {
			$issues = array_merge($issues, $this->validateString($value, $schema, $path));
		}

		if (is_int($value) || is_float($value)) {
			$issues = array_merge($issues, $this->validateNumber($value, $schema, $path));
		}

		return $issues;
	}

	/**
	 * @param array<int,string> $refStack
	 * @return array<int,array<string,mixed>>
	 */
	private function validateComposition(
		mixed $value,
		array $schema,
		string $path,
		mixed $rootSchema,
		int $depth,
		array $refStack
	): array {
		$issues = [];

		if (array_key_exists('allOf', $schema)) {
			if (!is_array($schema['allOf'])) {
				$issues[] = $this->issue($path, 'allOf', 'schema_invalid_all_of', 'Schema allOf must be an array.', [], $value);
			} else {
				foreach ($schema['allOf'] as $branch) {
					$issues = array_merge($issues, $this->validateNode($value, $branch, $path, $rootSchema, $depth + 1, $refStack));
				}
			}
		}

		if (array_key_exists('anyOf', $schema)) {
			if (!is_array($schema['anyOf']) || $schema['anyOf'] === []) {
				$issues[] = $this->issue($path, 'anyOf', 'schema_invalid_any_of', 'Schema anyOf must contain at least one branch.', [], $value);
			} else {
				$matched = false;
				foreach ($schema['anyOf'] as $branch) {
					if ($this->validateNode($value, $branch, $path, $rootSchema, $depth + 1, $refStack) === []) {
						$matched = true;
						break;
					}
				}
				if (!$matched) {
					$issues[] = $this->issue($path, 'anyOf', 'any_of_mismatch', 'Value does not satisfy any declared schema branch.', [], $value);
				}
			}
		}

		if (array_key_exists('oneOf', $schema)) {
			if (!is_array($schema['oneOf']) || $schema['oneOf'] === []) {
				$issues[] = $this->issue($path, 'oneOf', 'schema_invalid_one_of', 'Schema oneOf must contain at least one branch.', [], $value);
			} else {
				$matches = 0;
				foreach ($schema['oneOf'] as $branch) {
					if ($this->validateNode($value, $branch, $path, $rootSchema, $depth + 1, $refStack) === []) {
						$matches++;
					}
				}
				if ($matches !== 1) {
					$issues[] = $this->issue(
						$path,
						'oneOf',
						'one_of_mismatch',
						'Value must satisfy exactly one declared schema branch.',
						['matching_branches' => $matches],
						$value
					);
				}
			}
		}

		if (array_key_exists('not', $schema)) {
			if (!is_array($schema['not']) && !is_bool($schema['not']) && !$schema['not'] instanceof \stdClass) {
				$issues[] = $this->issue($path, 'not', 'schema_invalid_not', 'Schema not must contain a schema.', [], $value);
			} elseif ($this->validateNode($value, $schema['not'], $path, $rootSchema, $depth + 1, $refStack) === []) {
				$issues[] = $this->issue($path, 'not', 'not_mismatch', 'Value matches a schema that is explicitly disallowed.', [], $value);
			}
		}

		return $issues;
	}

	/**
	 * @return array{matches:bool,issues:array<int,array<string,mixed>>}
	 */
	private function validateType(mixed $value, array $schema, string $path): array {
		if (!array_key_exists('type', $schema)) {
			return ['matches' => true, 'issues' => []];
		}

		$types = $schema['type'];
		if (is_string($types)) {
			$types = [$types];
		}

		if (!is_array($types) || $types === []) {
			return [
				'matches' => false,
				'issues' => [$this->issue($path, 'type', 'schema_invalid_type', 'Schema type must be a string or a non-empty array of strings.', [], $value)]
			];
		}

		if (($schema['nullable'] ?? false) === true && !in_array('null', $types, true)) {
			$types[] = 'null';
		}

		foreach ($types as $type) {
			if (!is_string($type) || !$this->isKnownType($type)) {
				return [
					'matches' => false,
					'issues' => [$this->issue(
						$path,
						'type',
						'schema_unknown_type',
						'Schema declares an unsupported JSON type.',
						['type' => is_scalar($type) ? (string)$type : get_debug_type($type)],
						$value
					)]
				];
			}

			if ($this->matchesType($value, $type)) {
				return ['matches' => true, 'issues' => []];
			}
		}

		return [
			'matches' => false,
			'issues' => [$this->issue(
				$path,
				'type',
				'type_mismatch',
				'Value type does not satisfy the declared tool contract.',
				['expected_types' => array_values($types)],
				$value
			)]
		];
	}

	/**
	 * @param array<string,mixed> $value
	 * @param array<int,string> $refStack
	 * @return array<int,array<string,mixed>>
	 */
	private function validateObject(
		array $value,
		array $schema,
		string $path,
		mixed $rootSchema,
		int $depth,
		array $refStack
	): array {
		$issues = [];
		$count = count($value);

		if (isset($schema['minProperties']) && is_int($schema['minProperties']) && $count < $schema['minProperties']) {
			$issues[] = $this->issue($path, 'minProperties', 'min_properties', 'Object contains fewer properties than allowed.', ['minimum' => $schema['minProperties']], $value);
		}
		if (isset($schema['maxProperties']) && is_int($schema['maxProperties']) && $count > $schema['maxProperties']) {
			$issues[] = $this->issue($path, 'maxProperties', 'max_properties', 'Object contains more properties than allowed.', ['maximum' => $schema['maxProperties']], $value);
		}

		$required = $schema['required'] ?? [];
		if (!is_array($required)) {
			$issues[] = $this->issue($path, 'required', 'schema_invalid_required', 'Schema required must be an array of property names.', [], $value);
			$required = [];
		}

		foreach ($required as $property) {
			if (!is_string($property)) {
				$issues[] = $this->issue($path, 'required', 'schema_invalid_required_name', 'Required property names must be strings.', [], $value);
				continue;
			}
			if (!array_key_exists($property, $value)) {
				$issues[] = $this->issue(
					$this->propertyPath($path, $property),
					'required',
					'required_property_missing',
					'Required tool argument or result property is missing.',
					['property' => $property],
					null
				);
			}
		}

		$properties = $schema['properties'] ?? [];
		if ($properties instanceof \stdClass) {
			$properties = get_object_vars($properties);
		}
		if (!is_array($properties) || ($properties !== [] && array_is_list($properties))) {
			$issues[] = $this->issue($path, 'properties', 'schema_invalid_properties', 'Schema properties must be an object.', [], $value);
			$properties = [];
		}

		foreach ($properties as $property => $propertySchema) {
			if (!is_string($property) || !array_key_exists($property, $value)) {
				continue;
			}
			$issues = array_merge($issues, $this->validateNode(
				$value[$property],
				$propertySchema,
				$this->propertyPath($path, $property),
				$rootSchema,
				$depth + 1,
				$refStack
			));
		}

		$patternProperties = $schema['patternProperties'] ?? [];
		if ($patternProperties instanceof \stdClass) {
			$patternProperties = get_object_vars($patternProperties);
		}
		if (!is_array($patternProperties) || ($patternProperties !== [] && array_is_list($patternProperties))) {
			$issues[] = $this->issue($path, 'patternProperties', 'schema_invalid_pattern_properties', 'Schema patternProperties must be an object.', [], $value);
			$patternProperties = [];
		}

		foreach ($value as $property => $propertyValue) {
			$property = (string)$property;
			if (array_key_exists($property, $properties)) {
				continue;
			}

			$matchedPattern = false;
			foreach ($patternProperties as $pattern => $propertySchema) {
				$regex = $this->buildRegex((string)$pattern);
				if ($regex === null) {
					$issues[] = $this->issue($path, 'patternProperties', 'schema_invalid_pattern', 'Schema contains an invalid regular expression.', ['pattern' => (string)$pattern], $value);
					continue;
				}
				if (preg_match($regex, $property) === 1) {
					$matchedPattern = true;
					$issues = array_merge($issues, $this->validateNode(
						$propertyValue,
						$propertySchema,
						$this->propertyPath($path, $property),
						$rootSchema,
						$depth + 1,
						$refStack
					));
				}
			}

			if ($matchedPattern || !array_key_exists('additionalProperties', $schema)) {
				continue;
			}

			$additional = $schema['additionalProperties'];
			if ($additional === false) {
				$issues[] = $this->issue(
					$this->propertyPath($path, $property),
					'additionalProperties',
					'additional_property_not_allowed',
					'Property is not declared by the tool contract.',
					['property' => $property],
					$propertyValue
				);
			} elseif (is_array($additional) || is_bool($additional) || $additional instanceof \stdClass) {
				$issues = array_merge($issues, $this->validateNode(
					$propertyValue,
					$additional,
					$this->propertyPath($path, $property),
					$rootSchema,
					$depth + 1,
					$refStack
				));
			} else {
				$issues[] = $this->issue($path, 'additionalProperties', 'schema_invalid_additional_properties', 'Schema additionalProperties must be a boolean or schema.', [], $value);
			}
		}

		$dependentRequired = $schema['dependentRequired'] ?? [];
		if ($dependentRequired instanceof \stdClass) {
			$dependentRequired = get_object_vars($dependentRequired);
		}
		if (is_array($dependentRequired)) {
			foreach ($dependentRequired as $property => $dependencies) {
				if (!array_key_exists((string)$property, $value)) {
					continue;
				}
				if (!is_array($dependencies)) {
					$issues[] = $this->issue($path, 'dependentRequired', 'schema_invalid_dependent_required', 'Dependent required values must be arrays.', [], $value);
					continue;
				}
				foreach ($dependencies as $dependency) {
					if (is_string($dependency) && !array_key_exists($dependency, $value)) {
						$issues[] = $this->issue(
							$this->propertyPath($path, $dependency),
							'dependentRequired',
							'dependent_property_missing',
							'Property is required when another declared property is present.',
							['property' => (string)$property, 'dependency' => $dependency],
							null
						);
					}
				}
			}
		}

		return $issues;
	}

	/**
	 * @param array<int,mixed> $value
	 * @param array<int,string> $refStack
	 * @return array<int,array<string,mixed>>
	 */
	private function validateArray(
		array $value,
		array $schema,
		string $path,
		mixed $rootSchema,
		int $depth,
		array $refStack
	): array {
		$issues = [];
		$count = count($value);

		if (isset($schema['minItems']) && is_int($schema['minItems']) && $count < $schema['minItems']) {
			$issues[] = $this->issue($path, 'minItems', 'min_items', 'Array contains fewer items than allowed.', ['minimum' => $schema['minItems']], $value);
		}
		if (isset($schema['maxItems']) && is_int($schema['maxItems']) && $count > $schema['maxItems']) {
			$issues[] = $this->issue($path, 'maxItems', 'max_items', 'Array contains more items than allowed.', ['maximum' => $schema['maxItems']], $value);
		}

		if (($schema['uniqueItems'] ?? false) === true) {
			for ($left = 0; $left < $count; $left++) {
				for ($right = $left + 1; $right < $count; $right++) {
					if ($this->jsonEquals($value[$left], $value[$right])) {
						$issues[] = $this->issue($path . '[' . $right . ']', 'uniqueItems', 'duplicate_array_item', 'Array items must be unique.', ['first_index' => $left], $value[$right]);
						break 2;
					}
				}
			}
		}

		$prefixItems = $schema['prefixItems'] ?? [];
		if (is_array($prefixItems)) {
			foreach ($prefixItems as $index => $itemSchema) {
				if (!array_key_exists($index, $value)) {
					break;
				}
				$issues = array_merge($issues, $this->validateNode(
					$value[$index],
					$itemSchema,
					$path . '[' . $index . ']',
					$rootSchema,
					$depth + 1,
					$refStack
				));
			}
		}

		if (array_key_exists('items', $schema)) {
			$start = is_array($prefixItems) ? count($prefixItems) : 0;
			for ($index = $start; $index < $count; $index++) {
				$issues = array_merge($issues, $this->validateNode(
					$value[$index],
					$schema['items'],
					$path . '[' . $index . ']',
					$rootSchema,
					$depth + 1,
					$refStack
				));
			}
		}

		if (array_key_exists('contains', $schema)) {
			$matches = 0;
			foreach ($value as $index => $item) {
				if ($this->validateNode($item, $schema['contains'], $path . '[' . $index . ']', $rootSchema, $depth + 1, $refStack) === []) {
					$matches++;
				}
			}
			$minimum = is_int($schema['minContains'] ?? null) ? $schema['minContains'] : 1;
			$maximum = is_int($schema['maxContains'] ?? null) ? $schema['maxContains'] : null;
			if ($matches < $minimum || ($maximum !== null && $matches > $maximum)) {
				$issues[] = $this->issue(
					$path,
					'contains',
					'contains_mismatch',
					'Array does not contain the required number of matching items.',
					['matches' => $matches, 'minimum' => $minimum, 'maximum' => $maximum],
					$value
				);
			}
		}

		return $issues;
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	private function validateString(string $value, array $schema, string $path): array {
		$issues = [];
		$length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);

		if (isset($schema['minLength']) && is_int($schema['minLength']) && $length < $schema['minLength']) {
			$issues[] = $this->issue($path, 'minLength', 'min_length', 'String is shorter than allowed.', ['minimum' => $schema['minLength']], $value);
		}
		if (isset($schema['maxLength']) && is_int($schema['maxLength']) && $length > $schema['maxLength']) {
			$issues[] = $this->issue($path, 'maxLength', 'max_length', 'String is longer than allowed.', ['maximum' => $schema['maxLength']], $value);
		}
		if (array_key_exists('pattern', $schema)) {
			if (!is_string($schema['pattern'])) {
				$issues[] = $this->issue($path, 'pattern', 'schema_invalid_pattern', 'Schema pattern must be a string.', [], $value);
			} else {
				$regex = $this->buildRegex($schema['pattern']);
				if ($regex === null) {
					$issues[] = $this->issue($path, 'pattern', 'schema_invalid_pattern', 'Schema contains an invalid regular expression.', ['pattern' => $schema['pattern']], $value);
				} elseif (preg_match($regex, $value) !== 1) {
					$issues[] = $this->issue($path, 'pattern', 'pattern_mismatch', 'String does not match the declared pattern.', [], $value);
				}
			}
		}

		return $issues;
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	private function validateNumber(int|float $value, array $schema, string $path): array {
		$issues = [];

		if (isset($schema['minimum']) && is_numeric($schema['minimum']) && $value < (float)$schema['minimum']) {
			$issues[] = $this->issue($path, 'minimum', 'minimum', 'Number is below the declared minimum.', ['minimum' => $schema['minimum']], $value);
		}
		if (isset($schema['maximum']) && is_numeric($schema['maximum']) && $value > (float)$schema['maximum']) {
			$issues[] = $this->issue($path, 'maximum', 'maximum', 'Number is above the declared maximum.', ['maximum' => $schema['maximum']], $value);
		}

		if (isset($schema['exclusiveMinimum'])) {
			$minimum = is_bool($schema['exclusiveMinimum'])
				? (($schema['exclusiveMinimum'] && isset($schema['minimum']) && is_numeric($schema['minimum'])) ? (float)$schema['minimum'] : null)
				: (is_numeric($schema['exclusiveMinimum']) ? (float)$schema['exclusiveMinimum'] : null);
			if ($minimum !== null && $value <= $minimum) {
				$issues[] = $this->issue($path, 'exclusiveMinimum', 'exclusive_minimum', 'Number must be greater than the declared exclusive minimum.', ['minimum' => $minimum], $value);
			}
		}

		if (isset($schema['exclusiveMaximum'])) {
			$maximum = is_bool($schema['exclusiveMaximum'])
				? (($schema['exclusiveMaximum'] && isset($schema['maximum']) && is_numeric($schema['maximum'])) ? (float)$schema['maximum'] : null)
				: (is_numeric($schema['exclusiveMaximum']) ? (float)$schema['exclusiveMaximum'] : null);
			if ($maximum !== null && $value >= $maximum) {
				$issues[] = $this->issue($path, 'exclusiveMaximum', 'exclusive_maximum', 'Number must be less than the declared exclusive maximum.', ['maximum' => $maximum], $value);
			}
		}

		if (isset($schema['multipleOf']) && is_numeric($schema['multipleOf'])) {
			$multiple = (float)$schema['multipleOf'];
			if ($multiple <= 0.0) {
				$issues[] = $this->issue($path, 'multipleOf', 'schema_invalid_multiple_of', 'Schema multipleOf must be greater than zero.', [], $value);
			} else {
				$quotient = $value / $multiple;
				if (abs($quotient - round($quotient)) > 1e-9) {
					$issues[] = $this->issue($path, 'multipleOf', 'multiple_of', 'Number is not a multiple of the declared value.', ['multiple_of' => $multiple], $value);
				}
			}
		}

		return $issues;
	}

	private function isKnownType(string $type): bool {
		return in_array($type, ['object', 'array', 'string', 'integer', 'number', 'boolean', 'null'], true);
	}

	private function matchesType(mixed $value, string $type): bool {
		return match ($type) {
			'object' => $this->toObjectProperties($value) !== null,
			'array' => $this->isArrayValue($value),
			'string' => is_string($value),
			'integer' => is_int($value),
			'number' => (is_int($value) || is_float($value)) && is_finite((float)$value),
			'boolean' => is_bool($value),
			'null' => $value === null,
			default => false
		};
	}

	/**
	 * Empty PHP arrays are accepted as both JSON objects and arrays because PHP
	 * cannot preserve that distinction after normal decoding/serialization.
	 */
	private function isArrayValue(mixed $value): bool {
		return is_array($value) && ($value === [] || array_is_list($value));
	}

	/**
	 * @return array<string,mixed>|null
	 */
	private function toObjectProperties(mixed $value): ?array {
		if (is_array($value)) {
			if ($value === [] || !array_is_list($value)) {
				return $value;
			}
			return null;
		}

		if ($value instanceof \JsonSerializable) {
			$value = $value->jsonSerialize();
			if (is_array($value)) {
				return $value === [] || !array_is_list($value) ? $value : null;
			}
		}

		if (is_object($value)) {
			return get_object_vars($value);
		}

		return null;
	}

	/**
	 * @param array<int,mixed> $allowed
	 */
	private function matchesAnyEnumValue(mixed $value, array $allowed): bool {
		foreach ($allowed as $candidate) {
			if ($this->jsonEquals($value, $candidate)) {
				return true;
			}
		}
		return false;
	}

	private function jsonEquals(mixed $left, mixed $right): bool {
		if ((is_int($left) || is_float($left)) && (is_int($right) || is_float($right))) {
			return (float)$left === (float)$right;
		}

		return $this->normalizeComparable($left) == $this->normalizeComparable($right);
	}

	private function normalizeComparable(mixed $value): mixed {
		if ($value instanceof \JsonSerializable) {
			$value = $value->jsonSerialize();
		}
		if (is_object($value)) {
			$value = get_object_vars($value);
		}
		if (!is_array($value)) {
			return $value;
		}

		$result = [];
		foreach ($value as $key => $item) {
			$result[$key] = $this->normalizeComparable($item);
		}
		return $result;
	}

	/**
	 * @return array{found:bool,schema:mixed}
	 */
	private function resolveLocalReference(mixed $rootSchema, string $reference): array {
		if ($reference === '#') {
			return ['found' => true, 'schema' => $rootSchema];
		}
		if (!str_starts_with($reference, '#/')) {
			return ['found' => false, 'schema' => null];
		}

		$current = $rootSchema;
		$tokens = explode('/', substr($reference, 2));
		foreach ($tokens as $token) {
			$token = str_replace(['~1', '~0'], ['/', '~'], $token);
			if ($current instanceof \stdClass) {
				$current = get_object_vars($current);
			}
			if (!is_array($current) || !array_key_exists($token, $current)) {
				return ['found' => false, 'schema' => null];
			}
			$current = $current[$token];
		}

		return ['found' => true, 'schema' => $current];
	}

	private function propertyPath(string $path, string $property): string {
		if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $property) === 1) {
			return $path . '.' . $property;
		}

		$encoded = json_encode($property, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		return $path . '[' . ($encoded === false ? '"?"' : $encoded) . ']';
	}

	private function buildRegex(string $pattern): ?string {
		$regex = '~' . str_replace('~', '\\~', $pattern) . '~u';
		set_error_handler(static fn(): bool => true);
		try {
			$valid = preg_match($regex, '') !== false;
		} finally {
			restore_error_handler();
		}

		return $valid ? $regex : null;
	}

	/**
	 * @param array<int,array<string,mixed>> $issues
	 */
	private function hasSchemaIssue(array $issues): bool {
		foreach ($issues as $issue) {
			if (str_starts_with((string)($issue['code'] ?? ''), 'schema_')) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param array<string,mixed> $detail
	 * @return array<string,mixed>
	 */
	private function issue(
		string $path,
		string $keyword,
		string $code,
		string $message,
		array $detail,
		mixed $actual
	): array {
		return [
			'path' => $path,
			'keyword' => $keyword,
			'code' => $code,
			'message' => $message,
			'actual_type' => $this->jsonType($actual),
			'detail' => $detail
		];
	}

	private function jsonType(mixed $value): string {
		if ($value === null) {
			return 'null';
		}
		if (is_bool($value)) {
			return 'boolean';
		}
		if (is_int($value)) {
			return 'integer';
		}
		if (is_float($value)) {
			return 'number';
		}
		if (is_string($value)) {
			return 'string';
		}
		if ($this->isArrayValue($value)) {
			return 'array';
		}
		if ($this->toObjectProperties($value) !== null) {
			return 'object';
		}
		return get_debug_type($value);
	}
}
