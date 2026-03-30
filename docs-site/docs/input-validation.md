---
id: input-validation
title: Input Validation
sidebar_position: 6
---

# Input Validation

Phase 6 turns PachyBase validation into a predictable runtime layer instead of a loose set of CRUD checks.

## What this layer provides

- `services/Crud/EntityCrudValidator.php`: central validator for create, replace, and patch operations
- metadata-aware required and nullable rules
- field-level rule overrides from `modules/Crud/CrudEntityRegistry.php`
- structured `422` responses through the public API contract

## Rule sources

Validation now combines two sources:

- entity metadata from `database/Metadata/FieldDefinition.php`
- explicit field rules from the CRUD entity registry

This means the validator can enforce:

- readonly protection
- required vs nullable
- type validation
- string length boundaries
- numeric min/max
- enum lists
- email, URL, and UUID formats

## Supported rule keys

The current field-level rules include:

- `min`
- `max`
- `enum`
- `email`
- `url`
- `uuid`
- `required`
- `required_on_create`
- `required_on_replace`
- `required_on_patch`

## Operation behavior

- `create`: validates writable fields and enforces required fields
- `replace`: validates writable fields and enforces full replacement requirements
- `patch`: validates only sent fields unless a field is explicitly marked `required_on_patch`

## Error format

Invalid payloads fail with HTTP `422` and details shaped like:

```json
{
  "field": "setting_key",
  "code": "min",
  "message": "The field length must be at least 3 characters."
}
```

## Example rule declaration

```php
new CrudEntity(
    'system-settings',
    'pb_system_settings',
    validationRules: [
        'setting_key' => ['min' => 3, 'max' => 120],
        'value_type' => ['enum' => ['string', 'text', 'integer', 'float', 'boolean', 'json']],
    ]
);
```

## Validation coverage

The validation layer is covered by:

- unit tests for type validation and operation-aware behavior
- CRUD integration tests for min/max, enum, email, URL, UUID, conflicts, and replace vs patch
- HTTP kernel tests for structured `422` responses
