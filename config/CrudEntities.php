<?php

declare(strict_types=1);

use PachyBase\Modules\Crud\CrudEntity;

return [
    new CrudEntity(
        slug: 'system-settings',
        table: 'pb_system_settings',
        searchableFields: ['setting_key', 'setting_value'],
        filterableFields: ['id', 'setting_key', 'value_type', 'is_public'],
        sortableFields: ['id', 'setting_key', 'value_type', 'is_public', 'created_at', 'updated_at'],
        allowedFields: ['setting_key', 'setting_value', 'value_type', 'is_public'],
        hiddenFields: [],
        readOnlyFields: ['created_at', 'updated_at'],
        defaultSort: ['setting_key'],
        maxPerPage: 50,
        allowDelete: false,
        validationRules: [
            'setting_key' => ['min' => 3, 'max' => 120],
            'setting_value' => ['max' => 2000],
            'value_type' => ['enum' => ['string', 'text', 'integer', 'float', 'boolean', 'json']],
        ],
    ),
    new CrudEntity(
        slug: 'api-tokens',
        table: 'pb_api_tokens',
        searchableFields: ['name'],
        filterableFields: ['id', 'name', 'last_used_at'],
        sortableFields: ['id', 'name', 'last_used_at', 'created_at', 'updated_at'],
        allowedFields: ['name', 'token_hash', 'expires_at', 'last_used_at', 'is_active', 'user_id'],
        hiddenFields: ['token_hash'],
        readOnlyFields: ['created_at', 'updated_at'],
        defaultSort: ['-id'],
        maxPerPage: 100,
        validationRules: [
            'name' => ['min' => 3, 'max' => 120],
            'token_hash' => ['min' => 16, 'max' => 255],
        ],
    ),
];
