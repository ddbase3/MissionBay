<?php declare(strict_types=1);

namespace MissionBay\Test\Orchestrator\Validation;

use MissionBay\Orchestrator\Validation\JsonSchemaValidator;
use PHPUnit\Framework\TestCase;

final class JsonSchemaValidatorTest extends TestCase {

	public function testValidatesNestedObjectAndArrayContract(): void {
		$validator = new JsonSchemaValidator();
		$result = $validator->validate([
			'id' => 42,
			'tags' => ['base3', 'agent']
		], [
			'type' => 'object',
			'required' => ['id', 'tags'],
			'additionalProperties' => false,
			'properties' => [
				'id' => ['type' => 'integer', 'minimum' => 1],
				'tags' => [
					'type' => 'array',
					'minItems' => 1,
					'items' => ['type' => 'string', 'minLength' => 1]
				]
			]
		]);

		$this->assertTrue($result['valid']);
		$this->assertTrue($result['schema_valid']);
		$this->assertSame([], $result['issues']);
	}

	public function testReportsPathsWithoutReturningRejectedValues(): void {
		$validator = new JsonSchemaValidator();
		$result = $validator->validate([
			'id' => 'secret-value',
			'extra' => true
		], [
			'type' => 'object',
			'required' => ['id', 'name'],
			'additionalProperties' => false,
			'properties' => [
				'id' => ['type' => 'integer'],
				'name' => ['type' => 'string']
			]
		]);

		$this->assertFalse($result['valid']);
		$this->assertTrue($result['schema_valid']);
		$this->assertSame('$.name', $result['issues'][0]['path']);
		$this->assertSame('required_property_missing', $result['issues'][0]['code']);
		$this->assertStringNotContainsString('secret-value', json_encode($result['issues'], JSON_THROW_ON_ERROR));
	}

	public function testResolvesLocalSchemaReferences(): void {
		$validator = new JsonSchemaValidator();
		$result = $validator->validate(['id' => 5], [
			'$defs' => [
				'id' => ['type' => 'integer', 'minimum' => 1]
			],
			'type' => 'object',
			'required' => ['id'],
			'properties' => [
				'id' => ['$ref' => '#/$defs/id']
			]
		]);

		$this->assertTrue($result['valid']);
	}

	public function testMalformedSchemaIsDistinguishedFromValueMismatch(): void {
		$validator = new JsonSchemaValidator();
		$result = $validator->validate(['id' => 5], [
			'type' => 'object',
			'required' => 'id'
		]);

		$this->assertFalse($result['valid']);
		$this->assertFalse($result['schema_valid']);
		$this->assertSame('schema_invalid_required', $result['issues'][0]['code']);
	}
}
