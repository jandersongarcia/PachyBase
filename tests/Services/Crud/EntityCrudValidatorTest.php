<?php

declare(strict_types=1);

namespace Tests\Services\Crud;

use PachyBase\Database\Metadata\EntityDefinition;
use PachyBase\Database\Metadata\FieldDefinition;
use PachyBase\Http\ValidationException;
use PachyBase\Modules\Crud\CrudEntity;
use PachyBase\Services\Crud\EntityCrudValidator;
use PHPUnit\Framework\TestCase;

class EntityCrudValidatorTest extends TestCase
{
    public function testValidatesTypesAndNormalizesSupportedValues(): void
    {
        $validator = new EntityCrudValidator();
        $resource = $this->resource();
        $entity = $this->entity();

        $validated = $validator->validateForCreate($resource, $entity, [
            'name' => 'Valid Name',
            'email' => 'valid@example.com',
            'website' => 'https://example.com',
            'external_uuid' => '123e4567-e89b-12d3-a456-426614174000',
            'status' => 'published',
            'score' => '7',
            'is_active' => 'true',
            'published_on' => '2026-03-17',
            'published_at' => '2026-03-17T10:30:00+00:00',
            'alarm_time' => '10:30:00',
            'metadata_json' => '{"a":1}',
        ]);

        $this->assertSame(7, $validated['score']);
        $this->assertTrue($validated['is_active']);
        $this->assertSame('123e4567-e89b-12d3-a456-426614174000', $validated['external_uuid']);
        $this->assertSame('2026-03-17', $validated['published_on']);
    }

    public function testReturnsStructuredErrorsForInvalidPayloads(): void
    {
        $validator = new EntityCrudValidator();

        try {
            $validator->validateForCreate($this->resource(), $this->entity(), [
                'name' => 'No',
                'email' => 'invalid-email',
                'website' => 'invalid-url',
                'external_uuid' => 'invalid-uuid',
                'status' => 'unknown',
                'score' => 99,
                'is_active' => 'maybe',
                'published_on' => '17/03/2026',
                'published_at' => 'not-a-datetime',
                'alarm_time' => '25:99',
                'metadata_json' => '{bad',
            ]);
            $this->fail('Expected validation exception.');
        } catch (ValidationException $exception) {
            $codes = array_column($exception->details(), 'code');

            $this->assertContains('min', $codes);
            $this->assertContains('email', $codes);
            $this->assertContains('url', $codes);
            $this->assertContains('uuid', $codes);
            $this->assertContains('enum', $codes);
            $this->assertContains('max', $codes);
            $this->assertContains('boolean', $codes);
            $this->assertContains('date', $codes);
            $this->assertContains('datetime', $codes);
            $this->assertContains('time', $codes);
            $this->assertContains('json', $codes);
        }
    }

    public function testDistinguishesCreateReplaceAndPatchRequirements(): void
    {
        $validator = new EntityCrudValidator();
        $resource = $this->resource();
        $entity = $this->entity();

        $patched = $validator->validateForPatch($resource, $entity, [
            'score' => '5',
        ]);

        $this->assertSame(5, $patched['score']);

        $this->expectException(ValidationException::class);

        $validator->validateForReplace($resource, $entity, [
            'name' => 'Replace Only',
            'email' => 'replace@example.com',
            'website' => 'https://example.com/replace',
            'external_uuid' => '123e4567-e89b-12d3-a456-426614174001',
            'status' => 'draft',
            'score' => 2,
            'is_active' => true,
            'alarm_time' => '11:00:00',
            'metadata_json' => '{"x":1}',
        ]);
    }

    public function testRespectsAllowedAndConfiguredReadonlyFields(): void
    {
        $validator = new EntityCrudValidator();
        $resource = new CrudEntity(
            slug: 'validator-records',
            table: 'validator_records',
            allowedFields: ['name', 'email'],
            readOnlyFields: ['email'],
            tenantScoped: false
        );

        try {
            $validator->validateForCreate($resource, $this->entity(), [
                'name' => 'Allowed Name',
                'email' => 'readonly@example.com',
                'website' => 'https://example.com',
            ]);
            $this->fail('Expected validation exception.');
        } catch (ValidationException $exception) {
            $codesByField = [];

            foreach ($exception->details() as $detail) {
                $codesByField[$detail['field']][] = $detail['code'];
            }

            $this->assertContains('readonly_field', $codesByField['email'] ?? []);
            $this->assertContains('field_not_allowed', $codesByField['website'] ?? []);
        }
    }

    private function resource(): CrudEntity
    {
        return new CrudEntity(
            'validator-records',
            'validator_records',
            tenantScoped: false,
            validationRules: [
                'name' => ['min' => 3, 'max' => 120],
                'email' => ['email' => true],
                'website' => ['url' => true],
                'external_uuid' => ['uuid' => true],
                'status' => ['enum' => ['draft', 'published']],
                'score' => ['min' => 1, 'max' => 10],
                'published_on' => ['required_on_replace' => true],
            ]
        );
    }

    private function entity(): EntityDefinition
    {
        return new EntityDefinition(
            'validator_record',
            'validator_records',
            'public',
            'id',
            [
                new FieldDefinition('id', 'id', 'bigint', 'int8', true, false, true, false, null, true),
                new FieldDefinition('name', 'name', 'string', 'varchar', false, true, false, false, null, false, 120),
                new FieldDefinition('email', 'email', 'string', 'varchar', false, true, false, false, null, false, 190),
                new FieldDefinition('website', 'website', 'string', 'varchar', false, false, false, false, null, false, 255),
                new FieldDefinition('external_uuid', 'external_uuid', 'uuid', 'uuid', false, true, false, false),
                new FieldDefinition('status', 'status', 'string', 'varchar', false, true, false, false, 'draft', false, 30),
                new FieldDefinition('score', 'score', 'integer', 'int4', false, true, false, false),
                new FieldDefinition('is_active', 'is_active', 'boolean', 'bool', false, true, false, false, false),
                new FieldDefinition('published_on', 'published_on', 'date', 'date', false, false, false, false),
                new FieldDefinition('published_at', 'published_at', 'datetime', 'timestamptz', false, false, false, true),
                new FieldDefinition('alarm_time', 'alarm_time', 'time', 'time', false, true, false, false),
                new FieldDefinition('metadata_json', 'metadata_json', 'json', 'jsonb', false, true, false, false),
            ]
        );
    }
}
