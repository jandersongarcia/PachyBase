<?php

declare(strict_types=1);

namespace PachyBase\Database\Migrations;

use RuntimeException;

final class FilesystemMigrationLoader
{
    /**
     * @return array<int, MigrationInterface>
     */
    public function load(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $files = glob(rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.php');
        if ($files === false) {
            throw new RuntimeException(sprintf('Unable to read migrations directory: %s', $directory));
        }

        sort($files, SORT_STRING);

        $migrations = [];

        foreach ($files as $file) {
            $migration = (static function (string $path): mixed {
                return require $path;
            })($file);

            if (!$migration instanceof MigrationInterface) {
                throw new RuntimeException(
                    sprintf('Migration file "%s" must return an instance of %s.', $file, MigrationInterface::class)
                );
            }

            $migrations[] = $migration;
        }

        usort(
            $migrations,
            static fn(MigrationInterface $left, MigrationInterface $right): int => strcmp($left->version(), $right->version())
        );

        $duplicates = [];
        $seen = [];

        foreach ($migrations as $migration) {
            $version = $migration->version();

            if (isset($seen[$version])) {
                $duplicates[] = $version;
                continue;
            }

            $seen[$version] = true;
        }

        if ($duplicates !== []) {
            throw new RuntimeException(
                sprintf('Duplicate migration versions were found: %s', implode(', ', array_unique($duplicates)))
            );
        }

        return $migrations;
    }
}
