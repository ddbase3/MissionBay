<?php declare(strict_types=1);

namespace MissionBay\Test\Orchestrator\Validation;

use MissionBay\Orchestrator\Validation\JsonSchemaValidator;
use PHPUnit\Framework\TestCase;

final class JsonSchemaValidatorBoundaryCoverageTest extends TestCase {

	public function testBooleanSchemasAcceptOrRejectAnyValue(): void {
		$this->accept(['secret' => 'value'], true);
		$this->reject(['secret' => 'value'], false, 'false_schema');
	}

	public function testInvalidSchemaNodesAndReferencesAreReportedSeparately(): void {
		$this->reject('value', 42, 'schema_invalid_node');
		$this->reject('value', ['$ref' => 0], 'schema_invalid_ref');
		$this->reject('value', ['$ref' => ''], 'schema_invalid_ref');
		$this->reject('value', ['$ref' => 'https://example.test/schema'], 'schema_unresolved_ref');
		$this->reject('value', ['$ref' => '#/$defs/missing'], 'schema_unresolved_ref');
		$this->reject('value', ['$ref' => '#'], 'schema_circular_ref');
	}

	public function testCompositionAllOfCombinesConstraints(): void {
		$schema = ['allOf' => [['type' => 'integer'], ['minimum' => 3]]];
		$this->accept(5, $schema);
		$this->reject(2, $schema, 'minimum');
		$this->reject('5', $schema, 'type_mismatch');
		$this->reject(5, ['allOf' => 8], 'schema_invalid_all_of');
	}

	public function testCompositionAnyOfRequiresAtLeastOneMatchingBranch(): void {
		$schema = ['anyOf' => [['type' => 'string'], ['type' => 'integer']]];
		$this->accept('text', $schema);
		$this->accept(42, $schema);
		$this->reject(false, $schema, 'any_of_mismatch');
		$this->reject(0, ['anyOf' => []], 'schema_invalid_any_of');
		$this->reject(0, ['anyOf' => 'bad'], 'schema_invalid_any_of');
	}

	public function testCompositionOneOfRequiresExactlyOneMatchingBranch(): void {
		$schema = ['oneOf' => [['type' => 'integer'], ['minimum' => 2]]];
		$this->accept(1, $schema);
		$this->reject(3, $schema, 'one_of_mismatch');
		$this->reject('x', ['oneOf' => [['type' => 'integer'], ['type' => 'boolean']]], 'one_of_mismatch');
		$this->reject(3, ['oneOf' => []], 'schema_invalid_one_of');
		$this->reject(3, ['oneOf' => false], 'schema_invalid_one_of');
	}

	public function testNotRejectsMatchesAndMalformedSchemas(): void {
		$this->accept('value', ['not' => ['type' => 'integer']]);
		$this->reject(5, ['not' => ['type' => 'integer']], 'not_mismatch');
		$this->reject(null, ['not' => true], 'not_mismatch');
		$this->reject('value', ['not' => 'no'], 'schema_invalid_not');
	}

	public function testConstAndEnumPreserveJsonValueSemantics(): void {
		$this->accept(1.0, ['const' => 1]);
		$this->accept(['answer' => 2], ['enum' => [(object)['answer' => 2]]]);
		$this->reject(2, ['const' => 1], 'const_mismatch');
		$this->reject('private-value', ['enum' => ['other']], 'enum_mismatch');
		$this->reject(1, ['enum' => false], 'schema_invalid_enum');
	}

	public function testUnionTypesAndNullableAreAcceptedWithoutCoercion(): void {
		$this->accept(null, ['type' => 'integer', 'nullable' => true]);
		$this->accept('text', ['type' => ['integer', 'string']]);
		$this->accept(true, ['type' => 'boolean']);
		$this->accept(1.5, ['type' => 'number']);
		$this->accept([], ['type' => 'array']);
		$this->accept(new \stdClass(), ['type' => 'object']);
		$this->reject('1', ['type' => 'integer'], 'type_mismatch');
		$this->reject(1, ['type' => 'boolean'], 'type_mismatch');
	}

	public function testInvalidTypesAreSchemaFailures(): void {
		$this->reject(1, ['type' => []], 'schema_invalid_type');
		$this->reject(1, ['type' => 4], 'schema_invalid_type');
		$this->reject(1, ['type' => 'datetime'], 'schema_unknown_type');
		$this->reject('x', ['type' => ['integer', 7]], 'schema_unknown_type');
	}

	public function testPropertyCountsAndRequiredFieldsAreValidated(): void {
		$this->accept(['a' => 1], ['type' => 'object', 'minProperties' => 1, 'maxProperties' => 1, 'required' => ['a']]);
		$this->reject([], ['type' => 'object', 'minProperties' => 1], 'min_properties');
		$this->reject(['a' => 1, 'b' => 2], ['type' => 'object', 'maxProperties' => 1], 'max_properties');
		$this->reject([], ['type' => 'object', 'required' => ['missing']], 'required_property_missing');
		$this->reject([], ['type' => 'object', 'required' => 'key'], 'schema_invalid_required');
		$this->reject([], ['type' => 'object', 'required' => [null]], 'schema_invalid_required_name');
	}

	public function testObjectPropertySchemasAndUnusualKeysHavePrecisePaths(): void {
		$this->accept(['user-name' => 'bob'], ['type' => 'object', 'properties' => ['user-name' => ['type' => 'string']]]);
		$this->reject(['user-name' => 3], ['type' => 'object', 'properties' => ['user-name' => ['type' => 'string']]], 'type_mismatch', '$["user-name"]');
		$this->reject(['name' => 'x'], ['type' => 'object', 'properties' => ['name']], 'schema_invalid_properties');
		$this->reject(['name' => 'x'], ['type' => 'object', 'properties' => 1], 'schema_invalid_properties');
	}

	public function testPatternPropertiesMatchKeysAndValidateTheirValues(): void {
		$schema = ['type' => 'object', 'patternProperties' => ['^x_' => ['type' => 'integer']]];
		$this->accept(['x_count' => 5], $schema);
		$this->reject(['x_count' => 'wrong'], $schema, 'type_mismatch', '$.x_count');
		$this->reject(['x_count' => 5], ['type' => 'object', 'patternProperties' => ['^x_' => ['type' => 'string']]], 'type_mismatch');
		$this->reject(['x' => 2], ['type' => 'object', 'patternProperties' => ['(' => true]], 'schema_invalid_pattern');
		$this->reject(['x' => 2], ['type' => 'object', 'patternProperties' => ['x']], 'schema_invalid_pattern_properties');
	}

	public function testAdditionalPropertiesCanBeDeniedOrValidated(): void {
		$this->accept(['new' => 3], ['type' => 'object', 'additionalProperties' => ['type' => 'integer']]);
		$this->accept(['new' => 3], ['type' => 'object', 'additionalProperties' => true]);
		$this->reject(['extra' => 'private-value'], ['type' => 'object', 'additionalProperties' => false], 'additional_property_not_allowed', '$.extra');
		$this->reject(['extra' => 'str'], ['type' => 'object', 'additionalProperties' => ['type' => 'integer']], 'type_mismatch');
		$this->reject(['extra' => 1], ['type' => 'object', 'additionalProperties' => 'bad'], 'schema_invalid_additional_properties');
	}

	public function testDependentRequiredAppliesOnlyWhenTriggerExists(): void {
		$schema = ['type' => 'object', 'dependentRequired' => ['a' => ['b']]];
		$this->accept(['other' => 1], $schema);
		$this->accept(['a' => 1, 'b' => 2], $schema);
		$this->reject(['a' => 1], $schema, 'dependent_property_missing', '$.b');
		$this->reject(['a' => 1], ['type' => 'object', 'dependentRequired' => ['a' => 'b']], 'schema_invalid_dependent_required');
	}

	public function testArrayBoundsUniquenessAndPrefixSchemas(): void {
		$this->accept([1, 'two'], ['type' => 'array', 'prefixItems' => [['type' => 'integer'], ['type' => 'string']]]);
		$this->reject([], ['type' => 'array', 'minItems' => 1], 'min_items');
		$this->reject([1, 2], ['type' => 'array', 'maxItems' => 1], 'max_items');
		$this->reject([1, 1.0], ['type' => 'array', 'uniqueItems' => true], 'duplicate_array_item', '$[1]');
		$this->reject([1, 2], ['type' => 'array', 'prefixItems' => [['type' => 'integer'], ['type' => 'string']]], 'type_mismatch', '$[1]');
		$this->reject([1, 'bad'], ['type' => 'array', 'prefixItems' => [['type' => 'integer']], 'items' => ['type' => 'integer']], 'type_mismatch', '$[1]');
	}

	public function testArrayContainsRespectsMinimumAndMaximumMatches(): void {
		$schema = ['type' => 'array', 'contains' => ['type' => 'integer'], 'minContains' => 2, 'maxContains' => 2];
		$this->accept([1, 2, 'text'], $schema);
		$this->reject([1, 'text'], $schema, 'contains_mismatch');
		$this->reject([1, 2, 3], $schema, 'contains_mismatch');
		$this->reject(['x'], ['type' => 'array', 'contains' => ['type' => 'integer']], 'contains_mismatch');
	}

	public function testStringLengthsPatternsAndMalformedRegexes(): void {
		$this->accept('hello', ['type' => 'string', 'minLength' => 1, 'maxLength' => 5, 'pattern' => '^h']);
		$this->reject('', ['type' => 'string', 'minLength' => 1], 'min_length');
		$this->reject('too long', ['type' => 'string', 'maxLength' => 3], 'max_length');
		$this->reject('abc', ['type' => 'string', 'pattern' => '^z'], 'pattern_mismatch');
		$this->reject('abc', ['type' => 'string', 'pattern' => 12], 'schema_invalid_pattern');
		$this->reject('abc', ['type' => 'string', 'pattern' => '('], 'schema_invalid_pattern');
	}

	public function testNumberBoundsAndExclusiveLimits(): void {
		$this->accept(3, ['type' => 'integer', 'minimum' => 3, 'maximum' => 3]);
		$this->reject(2, ['type' => 'integer', 'minimum' => 3], 'minimum');
		$this->reject(4, ['type' => 'integer', 'maximum' => 3], 'maximum');
		$this->reject(3, ['type' => 'number', 'minimum' => 3, 'exclusiveMinimum' => true], 'exclusive_minimum');
		$this->reject(3, ['type' => 'number', 'maximum' => 3, 'exclusiveMaximum' => true], 'exclusive_maximum');
		$this->reject(3, ['type' => 'number', 'exclusiveMinimum' => 3], 'exclusive_minimum');
		$this->reject(3, ['type' => 'number', 'exclusiveMaximum' => 3], 'exclusive_maximum');
		$this->accept(4, ['exclusiveMinimum' => 3, 'exclusiveMaximum' => 5]);
	}

	public function testMultipleOfHandlesFractionsAndInvalidDivisors(): void {
		$this->accept(0.6, ['type' => 'number', 'multipleOf' => 0.2]);
		$this->reject(0.7, ['type' => 'number', 'multipleOf' => 0.2], 'multiple_of');
		$this->reject(5, ['type' => 'integer', 'multipleOf' => 0], 'schema_invalid_multiple_of');
		$this->reject(5, ['type' => 'integer', 'multipleOf' => -2], 'schema_invalid_multiple_of');
	}

	public function testReferenceEscapingAndObjectFormSchemas(): void {
		$schema = [
			'$defs' => ['a/b~c' => ['type' => 'number', 'minimum' => 2]],
			'properties' => ['item' => ['$ref' => '#/$defs/a~1b~0c']]
		];
		$this->accept(['item' => 3], $schema);
		$this->reject(['item' => 1], $schema, 'minimum', '$.item');
		$this->accept(['item' => 3], json_decode(json_encode($schema, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR));
	}

	public function testIssueLimitClampsToAtLeastOneAndNeverLeakesRejectedValues(): void {
		$result = (new JsonSchemaValidator(0))->validate(['secret' => 'canary-value', 'extra' => 'do-not-disclose'], [
			'type' => 'object', 'properties' => ['secret' => ['type' => 'integer']], 'additionalProperties' => false
		]);
		$this->assertCount(1, $result['issues']);
		$this->assertStringNotContainsString('canary-value', json_encode($result['issues'], JSON_THROW_ON_ERROR));
		$this->assertStringNotContainsString('do-not-disclose', json_encode($result['issues'], JSON_THROW_ON_ERROR));
	}

	public function testDepthLimitRejectsDeeplyNestedValuesWithoutDisclosingThem(): void {
		$result = (new JsonSchemaValidator(10, 1))->validate(['nested' => ['secret' => 'canary-value']], [
			'type' => 'object',
			'properties' => ['nested' => ['type' => 'object', 'properties' => ['secret' => ['type' => 'integer']]]]
		]);
		$this->assertFalse($result['schema_valid']);
		$this->assertSame('schema_max_depth_exceeded', $result['issues'][0]['code']);
		$this->assertStringNotContainsString('canary-value', json_encode($result['issues'], JSON_THROW_ON_ERROR));
	}

	private function accept(mixed $value, mixed $schema): void {
		$result = (new JsonSchemaValidator())->validate($value, $schema);
		$this->assertSame([], $result['issues'], json_encode($result['issues']) ?: '');
		$this->assertTrue($result['valid']);
		$this->assertTrue($result['schema_valid']);
	}

	private function reject(mixed $value, mixed $schema, string $code, ?string $path = null): void {
		$result = (new JsonSchemaValidator())->validate($value, $schema);
		$codes = array_column($result['issues'], 'code');
		$this->assertTrue(in_array($code, $codes, true), $code . ' not found in ' . implode(', ', $codes));
		$this->assertFalse($result['valid']);
		$this->assertSame(!str_starts_with($code, 'schema_'), $result['schema_valid']);
		if ($path !== null) {
			$index = array_search($code, $codes, true);
			$this->assertSame($path, $result['issues'][$index]['path']);
		}
	}
}
