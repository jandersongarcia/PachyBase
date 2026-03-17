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

class EntityCrudServiceIntegrationTest extends TestCase
{
    private ?PdoQueryExecutor $executor = null;
    private ?string $tableName = null;
    private ?EntityCrudService $service = null;

    protected function setUp(): void
    {
        Config::load(dirname(__DIR__, 3));

        $this->executor = new PdoQueryExecutor(Connection::getInstance()->getPDO());
        $suffix = bin2hex(random_bytes(4));
        $this->tableName = 'pb_phase6_records_' . $suffix;

        $this->createFixture();
        $this->seedFixture();

        $adapter = AdapterFactory::make();
        $registry = new CrudEntityRegistry([
            new CrudEntity(
                'phase6-records',
                (string) $this->tableName,
                ['title', 'notes', 'contact_email', 'website_url'],
                ['id', 'title', 'status', 'is_active', 'priority', 'contact_email'],
                ['id', 'title', 'status', 'priority', 'created_at'],
                [],
                ['title'],
                [
                    'title' => ['min' => 3, 'max' => 120],
                    'notes' => ['max' => 500],
                    'status' => ['enum' => ['draft', 'published', 'archived']],
                    'contact_email' => ['email' => true, 'max' => 190],
                    'website_url' => ['url' => true],
                    'external_uuid' => ['uuid' => true],
                    'priority' => ['min' => 1, 'max' => 5],
                    'published_on' => ['required_on_replace' => true],
                ]
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

    public function testListsRecordsWithPaginationFiltersSortingAndSearch(): void
    {
        $paginated = $this->service?->list(
            'phase6-records',
            new Request('GET', '/api/phase6-records', ['page' => 1, 'per_page' => 2, 'sort' => '-title'])
        );
        $filtered = $this->service?->list(
            'phase6-records',
            new Request('GET', '/api/phase6-records', ['filter' => ['status' => 'draft']])
        );
        $searched = $this->service?->list(
            'phase6-records',
            new Request('GET', '/api/phase6-records', ['search' => 'alpha'])
        );

        $this->assertSame(3, $paginated['total']);
        $this->assertCount(2, $paginated['items']);
        $this->assertSame(['Gamma', 'Beta'], array_column($paginated['items'], 'title'));
        $this->assertCount(2, $filtered['items']);
        $this->assertSame(['Alpha', 'Gamma'], array_column($filtered['items'], 'title'));
        $this->assertCount(1, $searched['items']);
        $this->assertSame('Alpha', $searched['items'][0]['title']);
        $this->assertIsBool($searched['items'][0]['is_active']);
        $this->assertSame(2, $searched['items'][0]['priority']);
    }

    public function testCreatesShowsUpdatesAndDeletesRecordsWithValidatedFields(): void
    {
        $created = $this->service?->create('phase6-records', [
            'title' => 'Delta',
            'notes' => 'Created by integration test',
            'status' => 'draft',
            'is_active' => true,
            'contact_email' => 'delta@example.com',
            'website_url' => 'https://example.com/delta',
            'external_uuid' => '123e4567-e89b-12d3-a456-426614174000',
            'priority' => 5,
            'published_on' => '2026-03-17',
        ]);

        $shown = $this->service?->show('phase6-records', (string) $created['id']);
        $replaced = $this->service?->replace('phase6-records', (string) $created['id'], [
            'title' => 'Delta Prime',
            'notes' => 'Replaced',
            'status' => 'published',
            'is_active' => false,
            'contact_email' => 'prime@example.com',
            'website_url' => 'https://example.com/prime',
            'external_uuid' => '123e4567-e89b-12d3-a456-426614174001',
            'priority' => 4,
            'published_on' => '2026-03-18',
        ]);
        $patched = $this->service?->patch('phase6-records', (string) $created['id'], [
            'notes' => 'Patched notes',
            'priority' => 3,
        ]);
        $deleted = $this->service?->delete('phase6-records', (string) $created['id']);

        $this->assertSame('Delta', $created['title']);
        $this->assertTrue($created['is_active']);
        $this->assertSame(5, $created['priority']);
        $this->assertSame($created['id'], $shown['id']);
        $this->assertSame('Delta Prime', $replaced['title']);
        $this->assertFalse($replaced['is_active']);
        $this->assertSame('Patched notes', $patched['notes']);
        $this->assertSame(3, $patched['priority']);
        $this->assertTrue($deleted['deleted']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(404);

        $this->service?->show('phase6-records', (string) $created['id']);
    }

    public function testRejectsInvalidPayloadsForRulesAndOperations(): void
    {
        try {
            $this->service?->create('phase6-records', [
                'title' => 'No',
                'status' => 'invalid',
                'is_active' => true,
                'contact_email' => 'not-an-email',
                'website_url' => 'bad-url',
                'external_uuid' => 'bad-uuid',
                'priority' => 9,
                'published_on' => '17/03/2026',
            ]);
            $this->fail('Expected validation exception.');
        } catch (ValidationException $exception) {
            $codes = array_column($exception->details(), 'code');

            $this->assertContains('min', $codes);
            $this->assertContains('enum', $codes);
            $this->assertContains('email', $codes);
            $this->assertContains('url', $codes);
            $this->assertContains('uuid', $codes);
            $this->assertContains('max', $codes);
            $this->assertContains('date', $codes);
        }

        try {
            $this->service?->replace('phase6-records', '1', [
                'title' => 'Alpha Prime',
                'status' => 'published',
                'is_active' => true,
                'contact_email' => 'alpha@example.com',
                'website_url' => 'https://example.com/alpha-prime',
                'external_uuid' => '123e4567-e89b-12d3-a456-426614174010',
                'priority' => 2,
            ]);
            $this->fail('Expected validation exception.');
        } catch (ValidationException $exception) {
            $this->assertSame('published_on', $exception->details()[0]['field']);
        }

        $patched = $this->service?->patch('phase6-records', '1', [
            'priority' => '4',
        ]);

        $this->assertSame(4, $patched['priority']);

        try {
            $this->service?->create('phase6-records', [
                'title' => 'Alpha',
                'status' => 'draft',
                'is_active' => true,
                'contact_email' => 'duplicate@example.com',
                'website_url' => 'https://example.com/duplicate',
                'external_uuid' => '123e4567-e89b-12d3-a456-426614174099',
                'priority' => 3,
                'published_on' => '2026-03-19',
            ]);
            $this->fail('Expected conflict exception.');
        } catch (RuntimeException $exception) {
            $this->assertSame(409, $exception->getCode());
        }
    }

    private function createFixture(): void
    {
        $driver = Connection::getInstance()->driver();

        if ($driver === 'pgsql') {
            $this->executor?->execute(
                sprintf(
                    "CREATE TABLE \"%s\" (\"id\" BIGSERIAL PRIMARY KEY, \"title\" VARCHAR(120) NOT NULL, \"notes\" TEXT NULL, \"status\" VARCHAR(30) NOT NULL DEFAULT 'draft', \"is_active\" BOOLEAN NOT NULL DEFAULT FALSE, \"contact_email\" VARCHAR(190) NOT NULL, \"website_url\" VARCHAR(255) NULL, \"external_uuid\" VARCHAR(36) NOT NULL, \"priority\" INTEGER NOT NULL DEFAULT 1, \"published_on\" DATE NOT NULL, \"created_at\" TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP, \"updated_at\" TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP, CONSTRAINT \"%s_title_unique\" UNIQUE (\"title\"))",
                    $this->tableName,
                    $this->tableName
                )
            );

            return;
        }

        $this->executor?->execute(
            sprintf(
                "CREATE TABLE `%s` (`id` BIGINT AUTO_INCREMENT PRIMARY KEY, `title` VARCHAR(120) NOT NULL, `notes` TEXT NULL, `status` VARCHAR(30) NOT NULL DEFAULT 'draft', `is_active` TINYINT(1) NOT NULL DEFAULT 0, `contact_email` VARCHAR(190) NOT NULL, `website_url` VARCHAR(255) NULL, `external_uuid` VARCHAR(36) NOT NULL, `priority` INT NOT NULL DEFAULT 1, `published_on` DATE NOT NULL, `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY `%s_title_unique` (`title`))",
                $this->tableName,
                $this->tableName
            )
        );
    }

    private function seedFixture(): void
    {
        $table = AdapterFactory::make()->quoteIdentifier((string) $this->tableName);

        foreach ([
            [
                'title' => 'Alpha',
                'notes' => 'Searchable alpha note',
                'status' => 'draft',
                'is_active' => true,
                'contact_email' => 'alpha@example.com',
                'website_url' => 'https://example.com/alpha',
                'external_uuid' => '123e4567-e89b-12d3-a456-426614174100',
                'priority' => 2,
                'published_on' => '2026-03-10',
            ],
            [
                'title' => 'Beta',
                'notes' => 'Published beta note',
                'status' => 'published',
                'is_active' => false,
                'contact_email' => 'beta@example.com',
                'website_url' => 'https://example.com/beta',
                'external_uuid' => '123e4567-e89b-12d3-a456-426614174101',
                'priority' => 3,
                'published_on' => '2026-03-11',
            ],
            [
                'title' => 'Gamma',
                'notes' => 'Another draft note',
                'status' => 'draft',
                'is_active' => true,
                'contact_email' => 'gamma@example.com',
                'website_url' => 'https://example.com/gamma',
                'external_uuid' => '123e4567-e89b-12d3-a456-426614174102',
                'priority' => 4,
                'published_on' => '2026-03-12',
            ],
        ] as $row) {
            $this->executor?->execute(
                sprintf(
                    'INSERT INTO %s (title, notes, status, is_active, contact_email, website_url, external_uuid, priority, published_on) VALUES (:title, :notes, :status, :is_active, :contact_email, :website_url, :external_uuid, :priority, :published_on)',
                    $table
                ),
                $row
            );
        }
    }
}
