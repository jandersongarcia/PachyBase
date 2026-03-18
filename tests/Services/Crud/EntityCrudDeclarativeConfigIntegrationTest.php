<?php

declare(strict_types=1);

namespace Tests\Services\Crud;

use PachyBase\Config;
use PachyBase\Database\AdapterFactory;
use PachyBase\Database\Connection;
use PachyBase\Database\Metadata\EntityIntrospector;
use PachyBase\Database\Query\PdoQueryExecutor;
use PachyBase\Database\Schema\SchemaInspector;
use PachyBase\Http\Request;
use PachyBase\Http\ValidationException;
use PachyBase\Modules\Crud\CrudEntity;
use PachyBase\Modules\Crud\CrudEntityRegistry;
use PachyBase\Services\Crud\EntityCrudService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class EntityCrudDeclarativeConfigIntegrationTest extends TestCase
{
    private ?PdoQueryExecutor $executor = null;
    private ?string $tableName = null;
    private ?EntityCrudService $service = null;

    protected function setUp(): void
    {
        Config::load(dirname(__DIR__, 3));

        $this->executor = new PdoQueryExecutor(Connection::getInstance()->getPDO());
        $suffix = bin2hex(random_bytes(4));
        $this->tableName = 'pb_phase8_records_' . $suffix;

        $this->createFixture();
        $this->seedFixture();

        $adapter = AdapterFactory::make();
        $registry = new CrudEntityRegistry([
            new CrudEntity(
                slug: 'phase8-records',
                table: (string) $this->tableName,
                searchableFields: ['title'],
                filterableFields: ['id', 'status'],
                sortableFields: ['id', 'title', 'created_at'],
                allowedFields: ['title', 'secret_note', 'status'],
                hiddenFields: ['secret_note'],
                readOnlyFields: ['status'],
                defaultSort: ['title'],
                maxPerPage: 2,
                allowDelete: false,
                validationRules: [
                    'title' => ['min' => 3, 'max' => 120],
                ],
                hooks: [
                    'before_create' => static function (array $payload): array {
                        if (isset($payload['title'])) {
                            $payload['title'] = trim((string) $payload['title']);
                        }

                        return $payload;
                    },
                    'before_update' => static function (array $payload): array {
                        if (isset($payload['title'])) {
                            $payload['title'] = trim((string) $payload['title']);
                        }

                        return $payload;
                    },
                    'after_create' => static function (array $item): array {
                        $item['hook_state'] = 'created';

                        return $item;
                    },
                    'after_update' => static function (array $item): array {
                        $item['hook_state'] = 'updated';

                        return $item;
                    },
                    'after_show' => static function (array $item): array {
                        $item['hook_state'] = 'shown';

                        return $item;
                    },
                    'after_list_item' => static function (array $item): array {
                        $item['hook_state'] = 'listed';

                        return $item;
                    },
                ]
            ),
            new CrudEntity(
                slug: 'phase8-hidden-records',
                table: (string) $this->tableName,
                exposed: false
            ),
        ]);

        $this->service = new EntityCrudService(
            $registry,
            $this->executor,
            $adapter,
            new EntityIntrospector(new SchemaInspector($adapter))
        );
    }

    protected function tearDown(): void
    {
        if ($this->executor !== null && $this->tableName !== null) {
            $adapter = AdapterFactory::make();
            $this->executor->execute(sprintf('DROP TABLE IF EXISTS %s', $adapter->quoteIdentifier($this->tableName)));
        }

        Connection::reset();
        Config::reset();
    }

    public function testAppliesDeclarativeFieldsHooksAndVisibilityRules(): void
    {
        $created = $this->service?->create('phase8-records', [
            'title' => '  Delta  ',
            'secret_note' => 'visible only in storage',
        ]);

        $listed = $this->service?->list(
            'phase8-records',
            new Request('GET', '/api/phase8-records', ['page' => 1, 'per_page' => 2, 'sort' => 'title'])
        );
        $shown = $this->service?->show('phase8-records', (string) $created['id']);
        $updated = $this->service?->patch('phase8-records', (string) $created['id'], [
            'title' => '  Delta Prime  ',
        ]);

        $this->assertSame('Delta', $created['title']);
        $this->assertSame('created', $created['hook_state']);
        $this->assertArrayNotHasKey('secret_note', $created);
        $this->assertCount(2, $listed['items']);
        $this->assertSame('listed', $listed['items'][0]['hook_state']);
        $this->assertSame('shown', $shown['hook_state']);
        $this->assertSame('Delta Prime', $updated['title']);
        $this->assertSame('updated', $updated['hook_state']);
    }

    public function testRejectsPaginationDeleteReadonlyAndHiddenEntityViolations(): void
    {
        try {
            $this->service?->list(
                'phase8-records',
                new Request('GET', '/api/phase8-records', ['page' => 1, 'per_page' => 3])
            );
            $this->fail('Expected pagination validation exception.');
        } catch (ValidationException $exception) {
            $this->assertSame('pagination', $exception->details()[0]['field']);
        }

        try {
            $this->service?->patch('phase8-records', '1', [
                'status' => 'published',
            ]);
            $this->fail('Expected readonly validation exception.');
        } catch (ValidationException $exception) {
            $this->assertSame('readonly_field', $exception->details()[0]['code']);
        }

        try {
            $this->service?->delete('phase8-records', '1');
            $this->fail('Expected method not allowed exception.');
        } catch (RuntimeException $exception) {
            $this->assertSame(405, $exception->getCode());
        }

        try {
            $this->service?->list('phase8-hidden-records', new Request('GET', '/api/phase8-hidden-records'));
            $this->fail('Expected hidden entity exception.');
        } catch (RuntimeException $exception) {
            $this->assertSame(404, $exception->getCode());
        }
    }

    private function createFixture(): void
    {
        $driver = Connection::getInstance()->driver();

        if ($driver === 'pgsql') {
            $this->executor?->execute(
                sprintf(
                    "CREATE TABLE \"%s\" (\"id\" BIGSERIAL PRIMARY KEY, \"title\" VARCHAR(120) NOT NULL, \"status\" VARCHAR(30) NOT NULL DEFAULT 'draft', \"secret_note\" TEXT NULL, \"created_at\" TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP, \"updated_at\" TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP)",
                    $this->tableName
                )
            );

            return;
        }

        $this->executor?->execute(
            sprintf(
                "CREATE TABLE `%s` (`id` BIGINT AUTO_INCREMENT PRIMARY KEY, `title` VARCHAR(120) NOT NULL, `status` VARCHAR(30) NOT NULL DEFAULT 'draft', `secret_note` TEXT NULL, `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP)",
                $this->tableName
            )
        );
    }

    private function seedFixture(): void
    {
        $table = AdapterFactory::make()->quoteIdentifier((string) $this->tableName);

        foreach ([
            ['title' => 'Alpha', 'status' => 'draft', 'secret_note' => 'alpha-secret'],
            ['title' => 'Beta', 'status' => 'draft', 'secret_note' => 'beta-secret'],
        ] as $row) {
            $this->executor?->execute(
                sprintf(
                    'INSERT INTO %s (title, status, secret_note) VALUES (:title, :status, :secret_note)',
                    $table
                ),
                $row
            );
        }
    }
}
