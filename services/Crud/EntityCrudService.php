<?php

declare(strict_types=1);

namespace PachyBase\Services\Crud;

use PachyBase\Database\AdapterFactory;
use PachyBase\Database\Adapters\DatabaseAdapterInterface;
use PachyBase\Database\Connection;
use PachyBase\Database\Metadata\EntityDefinition;
use PachyBase\Database\Metadata\EntityIntrospector;
use PachyBase\Database\Metadata\FieldDefinition;
use PachyBase\Database\Query\PdoQueryExecutor;
use PachyBase\Database\Query\QueryException;
use PachyBase\Database\Query\QueryExecutorInterface;
use PachyBase\Database\Schema\SchemaInspector;
use PachyBase\Http\Request;
use PachyBase\Http\ValidationException;
use PachyBase\Modules\Crud\CrudEntity;
use PachyBase\Modules\Crud\CrudEntityRegistry;
use PachyBase\Services\Tenancy\TenantQuotaService;
use RuntimeException;

final class EntityCrudService
{
    private readonly DatabaseAdapterInterface $adapter;
    private readonly QueryExecutorInterface $queryExecutor;
    private readonly EntityIntrospector $introspector;

    public function __construct(
        private readonly ?CrudEntityRegistry $registry = null,
        ?QueryExecutorInterface $queryExecutor = null,
        ?DatabaseAdapterInterface $adapter = null,
        ?EntityIntrospector $introspector = null,
        private readonly ?EntityCrudValidator $validator = null,
        private readonly ?EntityCrudSerializer $serializer = null,
        private readonly ?TenantQuotaService $tenantQuotas = null
    ) {
        $connection = Connection::getInstance();
        $this->queryExecutor = $queryExecutor ?? new PdoQueryExecutor($connection->getPDO());
        $this->adapter = $adapter ?? AdapterFactory::make($connection, $this->queryExecutor);
        $this->introspector = $introspector ?? new EntityIntrospector(new SchemaInspector($this->adapter));
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int, entity: string}
     */
    public function list(string $slug, Request $request): array
    {
        [$resource, $entity] = $this->resolveResourceMetadata($slug);
        [$page, $perPage] = $this->resolvePagination($resource, $request);
        [$whereSql, $bindings] = $this->buildWhereClause($resource, $entity, $request);
        $orderSql = $this->buildOrderClause($resource, $entity, $request);
        $quotedTable = $this->adapter->quoteIdentifier($resource->table);
        $offset = ($page - 1) * $perPage;

        $countSql = sprintf('SELECT COUNT(*) AS aggregate FROM %s%s', $quotedTable, $whereSql);
        $dataSql = sprintf(
            'SELECT %s FROM %s%s%s LIMIT %d OFFSET %d',
            $this->selectColumns($resource, $entity),
            $quotedTable,
            $whereSql,
            $orderSql,
            $perPage,
            $offset
        );

        $total = (int) ($this->queryExecutor->scalar($countSql, $bindings) ?? 0);
        $rows = $this->queryExecutor->select($dataSql, $bindings);

        return [
            'items' => array_map(
                fn(array $row): array => $this->runItemHook(
                    $resource,
                    'after_list_item',
                    $this->serializeResourceItem($resource, $entity, $row),
                    $entity
                ),
                $rows
            ),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'entity' => $resource->slug,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function show(string $slug, string $id, Request $request): array
    {
        [$resource, $entity] = $this->resolveResourceMetadata($slug);

        return $this->runItemHook(
            $resource,
            'after_show',
            $this->serializeResourceItem($resource, $entity, $this->findRowOrFail($resource, $entity, $id, $request)),
            $entity
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function create(string $slug, array $payload, Request $request): array
    {
        [$resource, $entity] = $this->resolveResourceMetadata($slug);
        $payload = $this->runPayloadHook($resource, 'before_create', $payload, $entity);
        $validated = $this->validator()->validateForCreate($resource, $entity, $payload);
        $validated = $this->applyTenantWriteGuard($resource, $entity, $validated, $request);
        $tenantId = $this->tenantId($resource, $entity, $request);

        if ($tenantId !== null) {
            ($this->tenantQuotas ?? new TenantQuotaService($this->queryExecutor, $this->registry ?? new CrudEntityRegistry()))
                ->assertCanCreateEntity($tenantId);
        }

        try {
            if ($this->adapter->driver() === 'pgsql') {
                $row = $this->insertReturning($resource, $entity, $validated);
            } else {
                $this->insert($resource, $validated);
                $row = $this->findInsertedRow($resource, $entity, $request);
            }
        } catch (QueryException $exception) {
            throw $this->translatePersistenceException($exception);
        }

        return $this->runItemHook(
            $resource,
            'after_create',
            $this->serializeResourceItem($resource, $entity, $row),
            $entity
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function replace(string $slug, string $id, array $payload, Request $request): array
    {
        return $this->updateRecord($slug, $id, $payload, true, $request);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function patch(string $slug, string $id, array $payload, Request $request): array
    {
        return $this->updateRecord($slug, $id, $payload, false, $request);
    }

    /**
     * @return array<string, mixed>
     */
    public function delete(string $slug, string $id, Request $request): array
    {
        [$resource, $entity] = $this->resolveResourceMetadata($slug);

        if (!$resource->allowsDelete()) {
            throw new RuntimeException(sprintf('Delete is disabled for entity "%s".', $resource->slug), 405);
        }

        $row = $this->findRowOrFail($resource, $entity, $id, $request);
        $serialized = $this->serializeResourceItem($resource, $entity, $row);
        $this->runEventHook($resource, 'before_delete', $serialized, $entity);
        $primaryField = $this->primaryField($entity);

        $this->queryExecutor->execute(
            sprintf(
                'DELETE FROM %s WHERE %s = :primary_key%s',
                $this->adapter->quoteIdentifier($resource->table),
                $this->adapter->quoteIdentifier($primaryField->column),
                $this->tenantWhereClause($resource, $entity, $request)[0]
            ),
            array_merge(
                ['primary_key' => $this->normalizeIdentifierValue($primaryField->type, $id)],
                $this->tenantWhereClause($resource, $entity, $request)[1]
            )
        );

        return $this->runItemHook(
            $resource,
            'after_delete',
            [
                'deleted' => true,
                'item' => $serialized,
            ],
            $entity
        );
    }

    /**
     * @return array{0: CrudEntity, 1: EntityDefinition}
     */
    private function resolveResourceMetadata(string $slug): array
    {
        $resource = $this->resource($slug);

        return [$resource, $this->metadata($resource)];
    }

    private function resource(string $slug): CrudEntity
    {
        $resource = ($this->registry ?? new CrudEntityRegistry())->find($slug);

        if (!$resource instanceof CrudEntity) {
            throw new RuntimeException(sprintf('Entity not found: %s', $slug), 404);
        }

        if (!$resource->isExposed()) {
            throw new RuntimeException(sprintf('Entity not found: %s', $slug), 404);
        }

        return $resource;
    }

    private function metadata(CrudEntity $resource): EntityDefinition
    {
        $entity = $this->introspector->inspectTable($resource->table);

        if ($entity->primaryField === null) {
            throw new RuntimeException(
                sprintf('Entity "%s" cannot be exposed without a single primary field.', $resource->slug),
                500
            );
        }

        $this->validateResourceDefinition($resource, $entity);

        return $entity;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function resolvePagination(CrudEntity $resource, Request $request): array
    {
        $page = (int) $request->query('page', 1);
        $perPage = (int) $request->query('per_page', 15);
        $maxPerPage = $resource->effectiveMaxPerPage();

        if ($page < 1 || $perPage < 1 || $perPage > $maxPerPage) {
            throw new ValidationException(details: [[
                'field' => 'pagination',
                'code' => 'invalid_pagination',
                'message' => sprintf(
                    'The pagination parameters must use page >= 1 and 1 <= per_page <= %d.',
                    $maxPerPage
                ),
            ]]);
        }

        return [$page, $perPage];
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function buildWhereClause(CrudEntity $resource, EntityDefinition $entity, Request $request): array
    {
        $bindings = [];
        $conditions = [];
        $fieldMap = $this->fieldMap($entity);
        $filters = $request->query('filter', []);

        if ($filters !== [] && !is_array($filters)) {
            throw new ValidationException(details: [[
                'field' => 'filter',
                'code' => 'invalid_filter',
                'message' => 'The filter parameter must be an object of field/value pairs.',
            ]]);
        }

        $allowedFilterFields = $resource->filterableFields !== [] ? $resource->filterableFields : array_keys($fieldMap);
        [$tenantConditions, $tenantBindings] = $this->tenantFilterConditions($resource, $entity, $request);
        $conditions = array_merge($conditions, $tenantConditions);
        $bindings = array_merge($bindings, $tenantBindings);

        foreach ($filters as $fieldName => $value) {
            if (!in_array($fieldName, $allowedFilterFields, true) || !isset($fieldMap[$fieldName])) {
                throw new ValidationException(details: [[
                    'field' => (string) $fieldName,
                    'code' => 'invalid_filter_field',
                    'message' => 'The field cannot be used as a filter.',
                ]]);
            }

            [$fieldConditions, $fieldBindings] = $this->buildFieldFilterConditions(
                (string) $fieldName,
                $fieldMap[$fieldName],
                $value
            );

            $conditions = array_merge($conditions, $fieldConditions);
            $bindings = array_merge($bindings, $fieldBindings);
        }

        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $searchConditions = [];
            $searchableFields = $this->resolveSearchableFields($resource, $entity);

            foreach ($searchableFields as $index => $fieldName) {
                $bindingName = 'search_' . $index;
                $searchConditions[] = sprintf(
                    'LOWER(CAST(%s AS %s)) LIKE :%s',
                    $this->adapter->quoteIdentifier($fieldMap[$fieldName]->column),
                    $this->adapter->driver() === 'pgsql' ? 'TEXT' : 'CHAR(255)',
                    $bindingName
                );
                $bindings[$bindingName] = '%' . strtolower($search) . '%';
            }

            if ($searchConditions !== []) {
                $conditions[] = '(' . implode(' OR ', $searchConditions) . ')';
            }
        }

        if ($conditions === []) {
            return ['', $bindings];
        }

        return [' WHERE ' . implode(' AND ', $conditions), $bindings];
    }

    /**
     * @return array{0: array<int, string>, 1: array<string, mixed>}
     */
    private function buildFieldFilterConditions(string $fieldName, FieldDefinition $field, mixed $value): array
    {
        if (!is_array($value)) {
            return $this->buildFilterCondition($fieldName, $field, 'eq', $value);
        }

        if ($value === []) {
            throw new ValidationException(details: [[
                'field' => $fieldName,
                'code' => 'invalid_filter_value',
                'message' => 'Structured filters must define at least one operator.',
            ]]);
        }

        if (array_is_list($value)) {
            throw new ValidationException(details: [[
                'field' => $fieldName,
                'code' => 'invalid_filter_value',
                'message' => 'Structured filters must use named operators like eq, ne, gt, gte, lt, lte, in, contains, or null.',
            ]]);
        }

        $conditions = [];
        $bindings = [];

        foreach ($value as $operator => $operatorValue) {
            if (!is_string($operator) || !$this->supportsFilterOperator($field, $operator)) {
                throw new ValidationException(details: [[
                    'field' => $fieldName,
                    'code' => 'invalid_filter_operator',
                    'message' => sprintf(
                        'Unsupported filter operator "%s". Allowed operators: %s.',
                        (string) $operator,
                        implode(', ', $this->supportedFilterOperators($field))
                    ),
                ]]);
            }

            [$operatorConditions, $operatorBindings] = $this->buildFilterCondition(
                $fieldName,
                $field,
                $operator,
                $operatorValue
            );

            $conditions = array_merge($conditions, $operatorConditions);
            $bindings = array_merge($bindings, $operatorBindings);
        }

        return [$conditions, $bindings];
    }

    /**
     * @return array{0: array<int, string>, 1: array<string, mixed>}
     */
    private function buildFilterCondition(string $fieldName, FieldDefinition $field, string $operator, mixed $value): array
    {
        $column = $this->adapter->quoteIdentifier($field->column);
        $bindingBase = sprintf('filter_%s_%s', $this->bindingToken($fieldName), $operator);

        return match ($operator) {
            'eq' => $value === null
                ? [[sprintf('%s IS NULL', $column)], []]
                : [[sprintf('%s = :%s', $column, $bindingBase)], [
                    $bindingBase => $this->normalizeScalarFilterValue($fieldName, $field, $operator, $value),
                ]],
            'ne' => $value === null
                ? [[sprintf('%s IS NOT NULL', $column)], []]
                : [[sprintf('%s <> :%s', $column, $bindingBase)], [
                    $bindingBase => $this->normalizeScalarFilterValue($fieldName, $field, $operator, $value),
                ]],
            'gt', 'gte', 'lt', 'lte' => [[
                sprintf('%s %s :%s', $column, $this->comparisonSqlOperator($operator), $bindingBase),
            ], [
                $bindingBase => $this->normalizeScalarFilterValue($fieldName, $field, $operator, $value),
            ]],
            'contains' => [[
                sprintf(
                    'LOWER(CAST(%s AS %s)) LIKE :%s',
                    $column,
                    $this->adapter->driver() === 'pgsql' ? 'TEXT' : 'CHAR(255)',
                    $bindingBase
                ),
            ], [
                $bindingBase => '%' . strtolower($this->normalizeContainsFilterValue($fieldName, $value)) . '%',
            ]],
            'in' => $this->buildInFilterCondition($fieldName, $field, $bindingBase, $value),
            'null' => $this->buildNullFilterCondition($fieldName, $column, $value),
            default => throw new ValidationException(details: [[
                'field' => $fieldName,
                'code' => 'invalid_filter_operator',
                'message' => sprintf('Unsupported filter operator "%s".', $operator),
            ]]),
        };
    }

    /**
     * @return array{0: array<int, string>, 1: array<string, mixed>}
     */
    private function buildInFilterCondition(string $fieldName, FieldDefinition $field, string $bindingBase, mixed $value): array
    {
        $values = $this->normalizeInFilterValues($fieldName, $field, $value);
        $placeholders = [];
        $bindings = [];

        foreach ($values as $index => $item) {
            $bindingName = sprintf('%s_%d', $bindingBase, $index);
            $placeholders[] = ':' . $bindingName;
            $bindings[$bindingName] = $item;
        }

        return [[
            sprintf(
                '%s IN (%s)',
                $this->adapter->quoteIdentifier($field->column),
                implode(', ', $placeholders)
            ),
        ], $bindings];
    }

    /**
     * @return array{0: array<int, string>, 1: array<string, mixed>}
     */
    private function buildNullFilterCondition(string $fieldName, string $column, mixed $value): array
    {
        $normalized = $this->normalizeNullFilterValue($fieldName, $value);

        return [[sprintf('%s IS %sNULL', $column, $normalized ? '' : 'NOT ')], []];
    }

    private function buildOrderClause(CrudEntity $resource, EntityDefinition $entity, Request $request): string
    {
        $sort = trim((string) $request->query('sort', ''));
        $sortParts = $sort !== '' ? explode(',', $sort) : $resource->defaultSort;
        $fieldMap = $this->fieldMap($entity);
        $allowedSortFields = $resource->sortableFields !== [] ? $resource->sortableFields : array_keys($fieldMap);
        $clauses = [];

        foreach ($sortParts as $sortPart) {
            $sortPart = trim((string) $sortPart);
            if ($sortPart === '') {
                continue;
            }

            $direction = str_starts_with($sortPart, '-') ? 'DESC' : 'ASC';
            $fieldName = ltrim($sortPart, '-');

            if (!in_array($fieldName, $allowedSortFields, true) || !isset($fieldMap[$fieldName])) {
                throw new ValidationException(details: [[
                    'field' => $fieldName,
                    'code' => 'invalid_sort_field',
                    'message' => 'The field cannot be used for sorting.',
                ]]);
            }

            $clauses[] = sprintf('%s %s', $this->adapter->quoteIdentifier($fieldMap[$fieldName]->column), $direction);
        }

        return $clauses === [] ? '' : ' ORDER BY ' . implode(', ', $clauses);
    }

    private function selectColumns(CrudEntity $resource, EntityDefinition $entity): string
    {
        $columns = [];

        foreach ($entity->fields as $field) {
            if ($resource->isHidden($field->name)) {
                continue;
            }

            $columns[] = $this->adapter->quoteIdentifier($field->column);
        }

        return implode(', ', $columns);
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private function insertReturning(CrudEntity $resource, EntityDefinition $entity, array $values): array
    {
        $bindings = [];
        $parameters = [];
        $columns = array_keys($values);

        foreach ($columns as $column) {
            $bindingName = 'insert_' . $column;
            $bindings[$bindingName] = $values[$column];
            $parameters[] = ':' . $bindingName;
        }

        $row = $this->queryExecutor->selectOne(
            sprintf(
                'INSERT INTO %s (%s) VALUES (%s) RETURNING %s',
                $this->adapter->quoteIdentifier($resource->table),
                implode(', ', array_map(fn(string $column): string => $this->adapter->quoteIdentifier($column), $columns)),
                implode(', ', $parameters),
                $this->selectColumns($resource, $entity)
            ),
            $bindings
        );

        if ($row === null) {
            throw new RuntimeException('The created record could not be reloaded.', 500);
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $values
     */
    private function insert(CrudEntity $resource, array $values): void
    {
        $bindings = [];
        $parameters = [];
        $columns = array_keys($values);

        foreach ($columns as $column) {
            $bindingName = 'insert_' . $column;
            $bindings[$bindingName] = $values[$column];
            $parameters[] = ':' . $bindingName;
        }

        $this->queryExecutor->execute(
            sprintf(
                'INSERT INTO %s (%s) VALUES (%s)',
                $this->adapter->quoteIdentifier($resource->table),
                implode(', ', array_map(fn(string $column): string => $this->adapter->quoteIdentifier($column), $columns)),
                implode(', ', $parameters)
            ),
            $bindings
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function findInsertedRow(CrudEntity $resource, EntityDefinition $entity, Request $request): array
    {
        $lastInsertId = Connection::getInstance()->getPDO()->lastInsertId();

        if ($lastInsertId === false || $lastInsertId === '0' || $lastInsertId === '') {
            throw new RuntimeException('The created record could not be reloaded.', 500);
        }

        return $this->findRowOrFail($resource, $entity, (string) $lastInsertId, $request);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function updateRecord(string $slug, string $id, array $payload, bool $replace, Request $request): array
    {
        [$resource, $entity] = $this->resolveResourceMetadata($slug);
        $currentRow = $this->findRowOrFail($resource, $entity, $id, $request);
        $payload = $this->runPayloadHook(
            $resource,
            'before_update',
            $payload,
            $entity,
            $this->serializeResourceItem($resource, $entity, $currentRow),
            $replace ? 'replace' : 'patch'
        );
        $validated = $replace
            ? $this->validator()->validateForReplace($resource, $entity, $payload)
            : $this->validator()->validateForPatch($resource, $entity, $payload);
        $validated = $this->applyTenantWriteGuard($resource, $entity, $validated, $request);

        if ($validated === []) {
            throw new ValidationException(details: [[
                'field' => 'payload',
                'code' => 'empty_payload',
                'message' => 'At least one writable field must be provided.',
            ]]);
        }

        try {
            if ($this->adapter->driver() === 'pgsql') {
                $row = $this->updateReturning($resource, $entity, $id, $validated, $request);
            } else {
                $this->update($resource, $entity, $id, $validated, $request);
                $row = $this->findRowOrFail($resource, $entity, $id, $request);
            }
        } catch (QueryException $exception) {
            throw $this->translatePersistenceException($exception);
        }

        return $this->runItemHook(
            $resource,
            'after_update',
            $this->serializeResourceItem($resource, $entity, $row),
            $entity
        );
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private function updateReturning(CrudEntity $resource, EntityDefinition $entity, string $id, array $values, Request $request): array
    {
        $primaryField = $this->primaryField($entity);
        $bindings = ['primary_key' => $this->normalizeIdentifierValue($primaryField->type, $id)];
        $assignments = [];
        [$tenantSql, $tenantBindings] = $this->tenantWhereClause($resource, $entity, $request);

        foreach ($values as $column => $value) {
            $bindingName = 'update_' . $column;
            $bindings[$bindingName] = $value;
            $assignments[] = sprintf('%s = :%s', $this->adapter->quoteIdentifier($column), $bindingName);
        }

        $row = $this->queryExecutor->selectOne(
            sprintf(
                'UPDATE %s SET %s WHERE %s = :primary_key%s RETURNING %s',
                $this->adapter->quoteIdentifier($resource->table),
                implode(', ', $assignments),
                $this->adapter->quoteIdentifier($primaryField->column),
                $tenantSql,
                $this->selectColumns($resource, $entity)
            ),
            array_merge($bindings, $tenantBindings)
        );

        if ($row === null) {
            throw new RuntimeException('The updated record could not be reloaded.', 500);
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $values
     */
    private function update(CrudEntity $resource, EntityDefinition $entity, string $id, array $values, Request $request): void
    {
        $primaryField = $this->primaryField($entity);
        $bindings = ['primary_key' => $this->normalizeIdentifierValue($primaryField->type, $id)];
        $assignments = [];
        [$tenantSql, $tenantBindings] = $this->tenantWhereClause($resource, $entity, $request);

        foreach ($values as $column => $value) {
            $bindingName = 'update_' . $column;
            $bindings[$bindingName] = $value;
            $assignments[] = sprintf('%s = :%s', $this->adapter->quoteIdentifier($column), $bindingName);
        }

        $this->queryExecutor->execute(
            sprintf(
                'UPDATE %s SET %s WHERE %s = :primary_key%s',
                $this->adapter->quoteIdentifier($resource->table),
                implode(', ', $assignments),
                $this->adapter->quoteIdentifier($primaryField->column),
                $tenantSql
            ),
            array_merge($bindings, $tenantBindings)
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function findRowOrFail(CrudEntity $resource, EntityDefinition $entity, string $id, Request $request): array
    {
        $primaryField = $this->primaryField($entity);
        [$tenantSql, $tenantBindings] = $this->tenantWhereClause($resource, $entity, $request);
        $row = $this->queryExecutor->selectOne(
            sprintf(
                'SELECT %s FROM %s WHERE %s = :primary_key%s LIMIT 1',
                $this->selectColumns($resource, $entity),
                $this->adapter->quoteIdentifier($resource->table),
                $this->adapter->quoteIdentifier($primaryField->column),
                $tenantSql
            ),
            array_merge(
                ['primary_key' => $this->normalizeIdentifierValue($primaryField->type, $id)],
                $tenantBindings
            )
        );

        if ($row === null) {
            throw new RuntimeException(sprintf('Record not found for entity "%s".', $resource->slug), 404);
        }

        return $row;
    }

    private function primaryField(EntityDefinition $entity): FieldDefinition
    {
        $field = $entity->field((string) $entity->primaryField);

        if ($field === null) {
            throw new RuntimeException('The entity primary field metadata is invalid.', 500);
        }

        return $field;
    }

    /**
     * @return array<string, FieldDefinition>
     */
    private function fieldMap(EntityDefinition $entity): array
    {
        $map = [];

        foreach ($entity->fields as $field) {
            $map[$field->name] = $field;
        }

        return $map;
    }

    /**
     * @return array<int, string>
     */
    private function resolveSearchableFields(CrudEntity $resource, EntityDefinition $entity): array
    {
        if ($resource->searchableFields !== []) {
            return $resource->searchableFields;
        }

        $searchable = [];

        foreach ($entity->fields as $field) {
            if (in_array($field->type, ['string', 'text'], true)) {
                $searchable[] = $field->name;
            }
        }

        return $searchable;
    }

    private function normalizeFilterValue(string $type, mixed $value): mixed
    {
        return match ($type) {
            'boolean' => in_array(strtolower((string) $value), ['1', 'true', 'yes', 'y', 'on'], true),
            'integer', 'bigint' => is_numeric($value) ? (int) $value : $value,
            'decimal', 'float' => is_numeric($value) ? (float) $value : $value,
            default => $value,
        };
    }

    private function normalizeScalarFilterValue(
        string $fieldName,
        FieldDefinition $field,
        string $operator,
        mixed $value
    ): mixed {
        if (is_array($value)) {
            throw new ValidationException(details: [[
                'field' => $fieldName,
                'code' => 'invalid_filter_value',
                'message' => sprintf('The "%s" filter operator only accepts a single scalar value.', $operator),
            ]]);
        }

        if ($value === null && !in_array($operator, ['eq', 'ne'], true)) {
            throw new ValidationException(details: [[
                'field' => $fieldName,
                'code' => 'invalid_filter_value',
                'message' => sprintf('The "%s" filter operator does not accept null values.', $operator),
            ]]);
        }

        return $this->normalizeFilterValue($field->type, $value);
    }

    private function normalizeContainsFilterValue(string $fieldName, mixed $value): string
    {
        if (is_array($value)) {
            throw new ValidationException(details: [[
                'field' => $fieldName,
                'code' => 'invalid_filter_value',
                'message' => 'The "contains" filter operator only accepts a single scalar value.',
            ]]);
        }

        $normalized = trim((string) $value);

        if ($normalized === '') {
            throw new ValidationException(details: [[
                'field' => $fieldName,
                'code' => 'invalid_filter_value',
                'message' => 'The "contains" filter operator cannot be empty.',
            ]]);
        }

        return $normalized;
    }

    /**
     * @return array<int, mixed>
     */
    private function normalizeInFilterValues(string $fieldName, FieldDefinition $field, mixed $value): array
    {
        if (is_array($value) && !array_is_list($value)) {
            throw new ValidationException(details: [[
                'field' => $fieldName,
                'code' => 'invalid_filter_value',
                'message' => 'The "in" filter operator accepts only a comma-separated string or a list of scalar values.',
            ]]);
        }

        $values = is_array($value)
            ? $value
            : array_values(
                array_filter(
                    array_map(static fn(string $item): string => trim($item), explode(',', (string) $value)),
                    static fn(string $item): bool => $item !== ''
                )
            );

        if ($values === []) {
            throw new ValidationException(details: [[
                'field' => $fieldName,
                'code' => 'invalid_filter_value',
                'message' => 'The "in" filter operator requires at least one value.',
            ]]);
        }

        return array_map(
            fn(mixed $item): mixed => $this->normalizeScalarFilterValue($fieldName, $field, 'in', $item),
            $values
        );
    }

    private function normalizeNullFilterValue(string $fieldName, mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_array($value)) {
            throw new ValidationException(details: [[
                'field' => $fieldName,
                'code' => 'invalid_filter_value',
                'message' => 'The "null" filter operator only accepts true or false.',
            ]]);
        }

        return match (strtolower(trim((string) $value))) {
            '1', 'true', 'yes', 'y', 'on' => true,
            '0', 'false', 'no', 'n', 'off' => false,
            default => throw new ValidationException(details: [[
                'field' => $fieldName,
                'code' => 'invalid_filter_value',
                'message' => 'The "null" filter operator only accepts true or false.',
            ]]),
        };
    }

    private function supportsFilterOperator(FieldDefinition $field, string $operator): bool
    {
        return in_array($operator, $this->supportedFilterOperators($field), true);
    }

    /**
     * @return array<int, string>
     */
    private function supportedFilterOperators(FieldDefinition $field): array
    {
        $operators = ['eq', 'ne', 'in', 'null'];

        if (in_array($field->type, ['integer', 'bigint', 'decimal', 'float', 'date', 'datetime', 'time'], true)) {
            $operators = array_merge($operators, ['gt', 'gte', 'lt', 'lte']);
        }

        if (in_array($field->type, ['string', 'text'], true)) {
            $operators[] = 'contains';
        }

        return $operators;
    }

    private function comparisonSqlOperator(string $operator): string
    {
        return match ($operator) {
            'gt' => '>',
            'gte' => '>=',
            'lt' => '<',
            'lte' => '<=',
            default => '=',
        };
    }

    private function bindingToken(string $value): string
    {
        $token = preg_replace('/[^a-z0-9_]+/i', '_', $value) ?? $value;

        return trim($token, '_');
    }

    private function normalizeIdentifierValue(string $type, string $id): mixed
    {
        return match ($type) {
            'integer', 'bigint' => preg_match('/^-?\d+$/', $id) === 1 ? (int) $id : $id,
            default => $id,
        };
    }

    private function translatePersistenceException(QueryException $exception): RuntimeException
    {
        $previousCode = $exception->getPrevious()?->getCode();
        $code = (string) ($previousCode ?: $exception->getCode());

        if (in_array($code, ['23000', '23505'], true)) {
            return new RuntimeException(
                'The request could not be completed because the record conflicts with an existing resource.',
                409,
                $exception
            );
        }

        return new RuntimeException('A database operation failed unexpectedly.', 500, $exception);
    }

    private function validator(): EntityCrudValidator
    {
        return $this->validator ?? new EntityCrudValidator();
    }

    private function serializer(): EntityCrudSerializer
    {
        return $this->serializer ?? new EntityCrudSerializer();
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function serializeResourceItem(CrudEntity $resource, EntityDefinition $entity, array $row): array
    {
        return $this->serializer()->serialize($entity, $row, $resource->hiddenFields);
    }

    private function validateResourceDefinition(CrudEntity $resource, EntityDefinition $entity): void
    {
        $fieldNames = array_keys($this->fieldMap($entity));

        foreach ([
            'searchableFields' => $resource->searchableFields,
            'filterableFields' => $resource->filterableFields,
            'sortableFields' => $resource->sortableFields,
            'hiddenFields' => $resource->hiddenFields,
            'allowedFields' => $resource->allowedFields,
            'readOnlyFields' => $resource->readOnlyFields,
        ] as $property => $fields) {
            foreach ($fields as $fieldName) {
                if (!in_array($fieldName, $fieldNames, true)) {
                    throw new RuntimeException(
                        sprintf(
                            'Entity "%s" references an unknown field "%s" in %s.',
                            $resource->slug,
                            $fieldName,
                            $property
                        ),
                        500
                    );
                }
            }
        }

        foreach ($entity->fields as $field) {
            if (
                $field->required
                && !$field->readOnly
                && !$field->nullable
                && $field->defaultValue === null
                && !$resource->isSystemManagedField($field->name)
                && !$resource->allowsWriteTo($field->name, $field->readOnly)
            ) {
                throw new RuntimeException(
                    sprintf(
                        'Entity "%s" makes required field "%s" unwritable without a default value.',
                        $resource->slug,
                        $field->name
                    ),
                    500
                );
            }
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function runPayloadHook(
        CrudEntity $resource,
        string $hookName,
        array $payload,
        EntityDefinition $entity,
        ?array $currentItem = null,
        ?string $operation = null
    ): array {
        $hook = $resource->hook($hookName);

        if ($hook === null) {
            return $payload;
        }

        $result = $hook($payload, $resource, $entity, $currentItem, $operation);

        if (!is_array($result)) {
            throw new RuntimeException(sprintf('The "%s" hook for entity "%s" must return an array.', $hookName, $resource->slug), 500);
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function runItemHook(CrudEntity $resource, string $hookName, array $item, EntityDefinition $entity): array
    {
        $hook = $resource->hook($hookName);

        if ($hook === null) {
            return $item;
        }

        $result = $hook($item, $resource, $entity);

        if (!is_array($result)) {
            throw new RuntimeException(sprintf('The "%s" hook for entity "%s" must return an array.', $hookName, $resource->slug), 500);
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function runEventHook(CrudEntity $resource, string $hookName, array $item, EntityDefinition $entity): void
    {
        $hook = $resource->hook($hookName);

        if ($hook === null) {
            return;
        }

        $hook($item, $resource, $entity);
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private function applyTenantWriteGuard(CrudEntity $resource, EntityDefinition $entity, array $values, Request $request): array
    {
        $tenantId = $this->tenantId($resource, $entity, $request);

        if ($tenantId !== null) {
            $values['tenant_id'] = $tenantId;
        }

        return $values;
    }

    private function tenantId(CrudEntity $resource, EntityDefinition $entity, Request $request): ?int
    {
        if (!$resource->tenantScoped) {
            return null;
        }

        if ($entity->field('tenant_id') === null) {
            throw new RuntimeException(
                sprintf('Entity "%s" must expose a tenant_id field to support tenant isolation.', $resource->slug),
                500
            );
        }

        $tenantId = $request->attribute('auth.tenant_id');

        if ($tenantId === null) {
            throw new RuntimeException('The request is missing tenant context for a tenant-scoped resource.', 500);
        }

        return (int) $tenantId;
    }

    /**
     * @return array{0: array<int, string>, 1: array<string, mixed>}
     */
    private function tenantFilterConditions(CrudEntity $resource, EntityDefinition $entity, Request $request): array
    {
        $tenantId = $this->tenantId($resource, $entity, $request);

        if ($tenantId === null) {
            return [[], []];
        }

        return [[sprintf('%s = :tenant_id', $this->adapter->quoteIdentifier('tenant_id'))], ['tenant_id' => $tenantId]];
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function tenantWhereClause(CrudEntity $resource, EntityDefinition $entity, Request $request): array
    {
        $tenantId = $this->tenantId($resource, $entity, $request);

        if ($tenantId === null) {
            return ['', []];
        }

        return [sprintf(' AND %s = :tenant_id', $this->adapter->quoteIdentifier('tenant_id')), ['tenant_id' => $tenantId]];
    }
}
