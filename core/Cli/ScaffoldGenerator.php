<?php

declare(strict_types=1);

namespace PachyBase\Cli;

use PachyBase\Modules\Crud\CrudEntity;
use PachyBase\Modules\Crud\CrudEntityRegistry;
use RuntimeException;

final class ScaffoldGenerator
{
    public function __construct(
        private readonly string $basePath
    ) {
    }

    public function createModule(string $name, bool $force = false): string
    {
        $studly = $this->studly($name);
        $path = 'modules/' . $studly . '/' . $studly . 'Module.php';

        return $this->writeFile($path, implode(PHP_EOL, [
            '<?php',
            '',
            'declare(strict_types=1);',
            '',
            'namespace PachyBase\\Modules\\' . $studly . ';',
            '',
            'use PachyBase\\Http\\Router;',
            '',
            'final class ' . $studly . 'Module',
            '{',
            '    public function register(Router $router): void',
            '    {',
            '    }',
            '}',
            '',
        ]), $force);
    }

    public function createController(string $name, bool $force = false): string
    {
        $studly = $this->controllerName($name);
        $resource = $this->resourceToken($name, '.');
        $path = 'api/Controllers/' . $studly . '.php';

        return $this->writeFile($path, implode(PHP_EOL, [
            '<?php',
            '',
            'declare(strict_types=1);',
            '',
            'namespace PachyBase\\Api\\Controllers;',
            '',
            'use PachyBase\\Http\\ApiResponse;',
            'use PachyBase\\Http\\Request;',
            '',
            'final class ' . $studly,
            '{',
            '    public function index(Request $request): void',
            '    {',
            '        ApiResponse::success(',
            '            [',
            "                'message' => '" . $studly . " is ready.',",
            '            ],',
            "            ['resource' => '" . $resource . ".index']",
            '        );',
            '    }',
            '}',
            '',
        ]), $force);
    }

    public function createService(string $name, bool $force = false): string
    {
        $studly = $this->serviceName($name);
        $path = 'services/' . $studly . '.php';

        return $this->writeFile($path, implode(PHP_EOL, [
            '<?php',
            '',
            'declare(strict_types=1);',
            '',
            'namespace PachyBase\\Services;',
            '',
            'final class ' . $studly,
            '{',
            '    /**',
            '     * @return array<string, mixed>',
            '     */',
            '    public function handle(array $payload = []): array',
            '    {',
            '        return $payload;',
            '    }',
            '}',
            '',
        ]), $force);
    }

    public function createMiddleware(string $name, bool $force = false): string
    {
        $studly = $this->middlewareName($name);
        $path = 'api/Middleware/' . $studly . '.php';

        return $this->writeFile($path, implode(PHP_EOL, [
            '<?php',
            '',
            'declare(strict_types=1);',
            '',
            'namespace PachyBase\\Api\\Middleware;',
            '',
            'use PachyBase\\Http\\Request;',
            '',
            'final class ' . $studly,
            '{',
            '    public function handle(Request $request, callable $next): void',
            '    {',
            '        $next();',
            '    }',
            '}',
            '',
        ]), $force);
    }

    public function createMigration(string $name, bool $force = false): string
    {
        $className = $this->studly($name);
        $fileName = gmdate('YmdHis') . '_' . $this->snake($name) . '.php';
        $path = 'database/migration-files/' . $fileName;

        return $this->writeFile($path, implode(PHP_EOL, [
            '<?php',
            '',
            'declare(strict_types=1);',
            '',
            'use PachyBase\\Database\\Adapters\\DatabaseAdapterInterface;',
            'use PachyBase\\Database\\Migrations\\AbstractSqlMigration;',
            '',
            'return new class extends AbstractSqlMigration',
            '{',
            '    /**',
            '     * @return array<int, string>',
            '     */',
            '    protected function upStatements(DatabaseAdapterInterface $adapter): array',
            '    {',
            '        return [',
            "            '-- TODO: implement migration {$className} up()'",
            '        ];',
            '    }',
            '',
            '    /**',
            '     * @return array<int, string>',
            '     */',
            '    protected function downStatements(DatabaseAdapterInterface $adapter): array',
            '    {',
            '        return [',
            "            '-- TODO: implement migration {$className} down()'",
            '        ];',
            '    }',
            '};',
            '',
        ]), $force);
    }

    public function createSeed(string $name, bool $force = false): string
    {
        $className = $this->studly($name);
        $fileName = gmdate('YmdHis') . '_' . $this->snake($name) . '.php';
        $path = 'database/seed-files/' . $fileName;

        return $this->writeFile($path, implode(PHP_EOL, [
            '<?php',
            '',
            'declare(strict_types=1);',
            '',
            'use PachyBase\\Database\\Adapters\\DatabaseAdapterInterface;',
            'use PachyBase\\Database\\Seeds\\AbstractSqlSeeder;',
            '',
            'return new class extends AbstractSqlSeeder',
            '{',
            '    /**',
            '     * @return array<int, string|array{sql: string, bindings?: array<int|string, mixed>}>',
            '     */',
            '    protected function statements(DatabaseAdapterInterface $adapter): array',
            '    {',
            '        return [',
            "            '-- TODO: implement seed {$className}'",
            '        ];',
            '    }',
            '};',
            '',
        ]), $force);
    }

    public function createTest(string $name, string $type = 'unit', bool $force = false): string
    {
        $studly = $this->testName($name);
        $suite = strtolower(trim($type)) === 'functional' ? 'Functional' : 'Unit';
        $path = 'tests/' . $suite . '/' . $studly . '.php';

        return $this->writeFile($path, implode(PHP_EOL, [
            '<?php',
            '',
            'declare(strict_types=1);',
            '',
            'namespace Tests\\' . $suite . ';',
            '',
            'use PHPUnit\\Framework\\TestCase;',
            '',
            'final class ' . $studly . ' extends TestCase',
            '{',
            '    public function testExample(): void',
            '    {',
            '        $this->assertTrue(true);',
            '    }',
            '}',
            '',
        ]), $force);
    }

    public function registerCrudEntity(string $name, ?string $table = null, bool $force = false): string
    {
        $slug = $this->slug($name);
        $table ??= 'pb_' . str_replace('-', '_', $slug);
        $configPath = $this->basePath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'CrudEntities.php';
        $entities = CrudEntityRegistry::loadConfiguredEntities($configPath);

        foreach ($entities as $entity) {
            if ($entity->hooks !== []) {
                throw new RuntimeException(sprintf('Cannot rewrite CRUD config while "%s" contains callable hooks.', $entity->slug));
            }

            if ($entity->slug === $slug && !$force) {
                throw new RuntimeException(sprintf('The CRUD entity "%s" already exists. Use --force to rewrite the file manually.', $slug));
            }
        }

        $filtered = array_values(array_filter(
            $entities,
            static fn(CrudEntity $entity): bool => $entity->slug !== $slug
        ));

        $filtered[] = new CrudEntity(
            slug: $slug,
            table: $table,
            searchableFields: ['name'],
            filterableFields: ['id'],
            sortableFields: ['id'],
            hiddenFields: [],
            defaultSort: ['-id'],
            validationRules: [],
            exposed: false,
            allowDelete: true,
            allowedFields: ['name'],
            maxPerPage: 100,
            readOnlyFields: ['created_at', 'updated_at']
        );

        usort(
            $filtered,
            static fn(CrudEntity $left, CrudEntity $right): int => strcmp($left->table, $right->table)
        );

        $entries = array_map(
            static fn(CrudEntity $entity): array => [
                'slug' => $entity->slug,
                'table' => $entity->table,
                'exposed' => $entity->exposed,
                'allow_delete' => $entity->allowDelete,
                'searchable_fields' => $entity->searchableFields,
                'filterable_fields' => $entity->filterableFields,
                'sortable_fields' => $entity->sortableFields,
                'allowed_fields' => $entity->allowedFields,
                'hidden_fields' => $entity->hiddenFields,
                'readonly_fields' => $entity->readOnlyFields,
                'default_sort' => $entity->defaultSort,
                'max_per_page' => $entity->maxPerPage,
                'validation_rules' => $entity->validationRules,
            ],
            $filtered
        );

        $contents = implode(PHP_EOL, [
            '<?php',
            '',
            'declare(strict_types=1);',
            '',
            'return [',
            implode(',' . PHP_EOL, array_map(
                fn(array $entry): string => '    ' . $this->exportPhpValue($entry, 1),
                $entries
            )),
            '];',
            '',
        ]);

        file_put_contents($configPath, $contents);

        return $configPath;
    }

    private function writeFile(string $relativePath, string $contents, bool $force): string
    {
        $path = $this->basePath . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
        $directory = dirname($path);

        if (!is_dir($directory) && !mkdir($concurrentDirectory = $directory, 0777, true) && !is_dir($concurrentDirectory)) {
            throw new RuntimeException(sprintf('Unable to create directory "%s".', $directory));
        }

        if (is_file($path) && !$force) {
            throw new RuntimeException(sprintf('The file "%s" already exists. Use --force to overwrite it.', $relativePath));
        }

        file_put_contents($path, $contents);

        return $path;
    }

    private function controllerName(string $name): string
    {
        $studly = $this->studly($name);

        return str_ends_with($studly, 'Controller') ? $studly : $studly . 'Controller';
    }

    private function middlewareName(string $name): string
    {
        $studly = $this->studly($name);

        return str_ends_with($studly, 'Middleware') ? $studly : $studly . 'Middleware';
    }

    private function serviceName(string $name): string
    {
        $studly = $this->studly($name);

        return str_ends_with($studly, 'Service') ? $studly : $studly . 'Service';
    }

    private function testName(string $name): string
    {
        $studly = $this->studly($name);

        return str_ends_with($studly, 'Test') ? $studly : $studly . 'Test';
    }

    private function resourceToken(string $name, string $separator): string
    {
        return str_replace('-', $separator, $this->slug($name));
    }

    private function studly(string $value): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_', '/'], ' ', trim($value))));
    }

    private function slug(string $value): string
    {
        $normalized = preg_replace('/[^A-Za-z0-9]+/', '-', trim($value)) ?? trim($value);

        return trim(strtolower($normalized), '-');
    }

    private function snake(string $value): string
    {
        return str_replace('-', '_', $this->slug($value));
    }

    private function exportPhpValue(mixed $value, int $depth = 0): string
    {
        if (is_array($value)) {
            if ($value === []) {
                return '[]';
            }

            $indent = str_repeat('    ', $depth);
            $childIndent = str_repeat('    ', $depth + 1);
            $isList = array_is_list($value);
            $lines = ['['];

            foreach ($value as $key => $item) {
                $line = $childIndent;

                if (!$isList) {
                    $line .= $this->exportPhpValue($key) . ' => ';
                }

                $line .= $this->exportPhpValue($item, $depth + 1) . ',';
                $lines[] = $line;
            }

            $lines[] = $indent . ']';

            return implode(PHP_EOL, $lines);
        }

        if (is_string($value)) {
            return "'" . str_replace(['\\', '\''], ['\\\\', '\\\''], $value) . "'";
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return 'null';
        }

        return (string) $value;
    }
}
