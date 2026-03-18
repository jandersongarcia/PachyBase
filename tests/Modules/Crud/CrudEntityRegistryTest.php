<?php

declare(strict_types=1);

namespace Tests\Modules\Crud;

use PachyBase\Modules\Crud\CrudEntityRegistry;
use PHPUnit\Framework\TestCase;

class CrudEntityRegistryTest extends TestCase
{
    public function testLoadsDeclarativeEntitiesFromConfigFile(): void
    {
        $configPath = tempnam(sys_get_temp_dir(), 'crud-entities-');

        if ($configPath === false) {
            $this->fail('Failed to create temporary config file.');
        }

        file_put_contents(
            $configPath,
            <<<'PHP'
<?php

return [
    [
        'slug' => 'custom-records',
        'table' => 'custom_records',
        'exposed' => false,
        'allow_delete' => false,
        'allowed_fields' => ['title'],
        'hidden_fields' => ['internal_note'],
        'readonly_fields' => ['status'],
        'max_per_page' => 7,
        'validation_rules' => [
            'title' => ['min' => 3],
        ],
    ],
];
PHP
        );

        try {
            $entity = (new CrudEntityRegistry([], $configPath))->find('custom-records');

            $this->assertNotNull($entity);
            $this->assertFalse($entity->isExposed());
            $this->assertFalse($entity->allowsDelete());
            $this->assertSame(['title'], $entity->allowedFields);
            $this->assertSame(['internal_note'], $entity->hiddenFields);
            $this->assertSame(['status'], $entity->readOnlyFields);
            $this->assertSame(7, $entity->effectiveMaxPerPage());
            $this->assertSame(['min' => 3], $entity->rulesFor('title'));
        } finally {
            @unlink($configPath);
        }
    }
}
