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
        private readonly ?EntityCrudSerializer $serializer = null
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
    public function show(string $slug, string $id): array
    {
        [$resource, $entity] = $this->resolveResourceMetadata($slug);

        return $this->runItemHook(
            $resource,
            'after_show',
            $this->serializeResourceItem($resource, $entity, $this->findRowOrFail($resource, $entity, $id)),
            $entity
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function create(string $slug, array $payload): array
    {
        [$resource, $entity] = $this->resolveResourceMetadata($slug);
        $payload = $this->runPayloadHook($resource, 'before_create', $payload, $entity);
        $validated = $this->validator()->validateForCreate($resource, $entity, $payload);

        try {
            if ($this->adapter->driver() === 'pgsql') {
                $row = $this->insertReturning($resource, $entity, $validated);
            } else {
                $this->insert($resource, $validated);
                $row = $this->findInsertedRow($resource, $entity);
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
    public function replace(string $slug, string $id, array $payload): array
    {
        return $this->updateRecord($slug, $id, $payload, true);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function patch(string $slug, string $id, array $payload): array
    {
        return $this->updateRecord($slug, $id, $payload, false);
    }

    /**
     * @return array<string, mixed>
     */
    public function delete(string $slug, string $id): array
    {
        [$resource, $entity] = $this->resolveResourceMetadata($slug);

        if (!$resource->allowsDelete()) {
            throw new RuntimeException(sprintf('Delete is disabled for entity "%s".', $resource->slug), 405);
        }

        $row = $this->findRowOrFail($resource, $entity, $id);
        $serialized = $this->serializeResourceItem($resource, $entity, $row);
        $this->runEventHook($resource, 'before_delete', $serialized, $entity);
        $primaryField = $this->primaryField($entity);

        $this->queryExecutor->execute(
            sprintf(
                'DELETE FROM %s WHERE %s = :primary_key',
                $this->adapter->quoteIdentifier($resource->table),
                $this->adapter->quoteIdentifier($primaryField->column)
            ),
            ['primary_key' => $this->normalizeIdentifierValue($primaryField->type, $id)]
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

        foreach ($filters as $fieldName => $value) {
            if (!in_array($fieldName, $allowedFilterFields, true) || !isset($fieldMap[$fieldName])) {
                throw new ValidationException(details: [[
                    'field' => (string) $fieldName,
                    'code' => 'invalid_filter_field',
                    'message' => 'The field cannot be used as a filter.',
                ]]);
            }

            if (is_array($value)) {
                throw new ValidationException(details: [[
                    'field' => (string) $fieldName,
                    'code' => 'invalid_filter_value',
                    'message' => 'Simple filters only accept a single scalar value.',
                ]]);
            }

            $bindingName = 'filter_' . $fieldName;
            $conditions[] = sprintf(
                '%s = :%s',
                $this->adapter->quoteIdentifier($fieldMap[$fieldName]->column),
                $bindingName
            );
            $bindings[$bindingName] = $this->normalizeFilterValue($fieldMap[$fieldName]->type, $value);
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
    private function findInsertedRow(CrudEntity $resource, EntityDefinition $entity): array
    {
        $lastInsertId = Connection::getInstance()->getPDO()->lastInsertId();

        if ($lastInsertId === false || $lastInsertId === '0' || $lastInsertId === '') {
            throw new RuntimeException('The created record could not be reloaded.', 500);
        }

        return $this->findRowOrFail($resource, $entity, (string) $lastInsertId);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function updateRecord(string $slug, string $id, array $payload, bool $replace): array
    {
        [$resource, $entity] = $this->resolveResourceMetadata($slug);
        $currentRow = $this->findRowOrFail($resource, $entity, $id);
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

        if ($validated === []) {
            throw new ValidationException(details: [[
                'field' => 'payload',
                'code' => 'empty_payload',
                'message' => 'At least one writable field must be provided.',
            ]]);
        }

        try {
            if ($this->adapter->driver() === 'pgsql') {
                $row = $this->updateReturning($resource, $entity, $id, $validated);
            } else {
                $this->update($resource, $entity, $id, $validated);
                $row = $this->findRowOrFail($resource, $entity, $id);
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
    private function updateReturning(CrudEntity $resource, EntityDefinition $entity, string $id, array $values): array
    {
        $primaryField = $this->primaryField($entity);
        $bindings = ['primary_key' => $this->normalizeIdentifierValue($primaryField->type, $id)];
        $assignments = [];

        foreach ($values as $column => $value) {
            $bindingName = 'update_' . $column;
            $bindings[$bindingName] = $value;
            $assignments[] = sprintf('%s = :%s', $this->adapter->quoteIdentifier($column), $bindingName);
        }

        $row = $this->queryExecutor->selectOne(
            sprintf(
                'UPDATE %s SET %s WHERE %s = :primary_key RETURNING %s',
                $this->adapter->quoteIdentifier($resource->table),
                implode(', ', $assignments),
                $this->adapter->quoteIdentifier($primaryField->column),
                $this->selectColumns($resource, $entity)
            ),
            $bindings
        );

        if ($row === null) {
            throw new RuntimeException('The updated record could not be reloaded.', 500);
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $values
     */
    private function update(CrudEntity $resource, EntityDefinition $entity, string $id, array $values): void
    {
        $primaryField = $this->primaryField($entity);
        $bindings = ['primary_key' => $this->normalizeIdentifierValue($primaryField->type, $id)];
        $assignments = [];

        foreach ($values as $column => $value) {
            $bindingName = 'update_' . $column;
            $bindings[$bindingName] = $value;
            $assignments[] = sprintf('%s = :%s', $this->adapter->quoteIdentifier($column), $bindingName);
        }

        $this->queryExecutor->execute(
            sprintf(
                'UPDATE %s SET %s WHERE %s = :primary_key',
                $this->adapter->quoteIdentifier($resource->table),
                implode(', ', $assignments),
                $this->adapter->quoteIdentifier($primaryField->column)
            ),
            $bindings
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function findRowOrFail(CrudEntity $resource, EntityDefinition $entity, string $id): array
    {
        $primaryField = $this->primaryField($entity);
        $row = $this->queryExecutor->selectOne(
            sprintf(
                'SELECT %s FROM %s WHERE %s = :primary_key LIMIT 1',
                $this->selectColumns($resource, $entity),
                $this->adapter->quoteIdentifier($resource->table),
                $this->adapter->quoteIdentifier($primaryField->column)
            ),
            ['primary_key' => $this->normalizeIdentifierValue($primaryField->type, $id)]
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
}
