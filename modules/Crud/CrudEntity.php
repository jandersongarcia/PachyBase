<?php

declare(strict_types=1);

namespace PachyBase\Modules\Crud;

final readonly class CrudEntity
{
    /**
     * @param array<int, string> $searchableFields
     * @param array<int, string> $filterableFields
     * @param array<int, string> $sortableFields
     * @param array<int, string> $hiddenFields
     * @param array<int, string> $defaultSort
     * @param array<string, array<string, mixed>> $validationRules
     * @param array<int, string> $allowedFields
     * @param array<int, string> $readOnlyFields
     * @param array<string, callable> $hooks
     */
    public function __construct(
        public string $slug,
        public string $table,
        public array $searchableFields = [],
        public array $filterableFields = [],
        public array $sortableFields = [],
        public array $hiddenFields = [],
        public array $defaultSort = [],
        public array $validationRules = [],
        public bool $exposed = true,
        public bool $allowDelete = true,
        public array $allowedFields = [],
        public int $maxPerPage = 100,
        public array $readOnlyFields = [],
        public array $hooks = [],
        public bool $tenantScoped = true
    ) {
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            slug: (string) ($config['slug'] ?? ''),
            table: (string) ($config['table'] ?? ''),
            searchableFields: self::stringList($config['searchable_fields'] ?? []),
            filterableFields: self::stringList($config['filterable_fields'] ?? []),
            sortableFields: self::stringList($config['sortable_fields'] ?? []),
            hiddenFields: self::stringList($config['hidden_fields'] ?? []),
            defaultSort: self::stringList($config['default_sort'] ?? []),
            validationRules: is_array($config['validation_rules'] ?? null) ? $config['validation_rules'] : [],
            exposed: (bool) ($config['exposed'] ?? true),
            allowDelete: (bool) ($config['allow_delete'] ?? true),
            allowedFields: self::stringList($config['allowed_fields'] ?? []),
            maxPerPage: max(1, (int) ($config['max_per_page'] ?? 100)),
            readOnlyFields: self::stringList($config['readonly_fields'] ?? []),
            hooks: is_array($config['hooks'] ?? null) ? $config['hooks'] : [],
            tenantScoped: (bool) ($config['tenant_scoped'] ?? true)
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function rulesFor(string $field): array
    {
        return $this->validationRules[$field] ?? [];
    }

    public function isExposed(): bool
    {
        return $this->exposed;
    }

    public function allowsDelete(): bool
    {
        return $this->allowDelete;
    }

    public function allowsWriteTo(string $fieldName, bool $schemaReadOnly = false): bool
    {
        return !$this->isReadOnly($fieldName, $schemaReadOnly) && $this->isFieldAllowed($fieldName);
    }

    public function isFieldAllowed(string $fieldName): bool
    {
        return $this->allowedFields === [] || in_array($fieldName, $this->allowedFields, true);
    }

    public function isHidden(string $fieldName): bool
    {
        return in_array($fieldName, $this->hiddenFields, true);
    }

    public function isReadOnly(string $fieldName, bool $schemaReadOnly = false): bool
    {
        return $schemaReadOnly || in_array($fieldName, $this->readOnlyFields, true);
    }

    public function effectiveMaxPerPage(): int
    {
        return max(1, $this->maxPerPage);
    }

    public function isSystemManagedField(string $fieldName): bool
    {
        return $this->tenantScoped && $fieldName === 'tenant_id';
    }

    public function hook(string $name): ?callable
    {
        $hook = $this->hooks[$name] ?? null;

        return is_callable($hook) ? $hook : null;
    }

    /**
     * @param mixed $values
     * @return array<int, string>
     */
    private static function stringList(mixed $values): array
    {
        if (!is_array($values)) {
            return [];
        }

        return array_values(
            array_map(
                static fn(mixed $value): string => (string) $value,
                $values
            )
        );
    }
}
