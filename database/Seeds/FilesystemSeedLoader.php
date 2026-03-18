<?php

declare(strict_types=1);

namespace PachyBase\Database\Seeds;

use RuntimeException;

final class FilesystemSeedLoader
{
    /**
     * @return array<int, SeederInterface>
     */
    public function load(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $files = glob(rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.php');
        if ($files === false) {
            throw new RuntimeException(sprintf('Unable to read seeds directory: %s', $directory));
        }

        sort($files, SORT_STRING);

        $seeders = [];

        foreach ($files as $file) {
            $seeder = (static function (string $path): mixed {
                return require $path;
            })($file);

            if (!$seeder instanceof SeederInterface) {
                throw new RuntimeException(
                    sprintf('Seed file "%s" must return an instance of %s.', $file, SeederInterface::class)
                );
            }

            $seeders[] = $seeder;
        }

        usort(
            $seeders,
            static fn(SeederInterface $left, SeederInterface $right): int => strcmp($left->name(), $right->name())
        );

        $duplicates = [];
        $seen = [];

        foreach ($seeders as $seeder) {
            $name = $seeder->name();

            if (isset($seen[$name])) {
                $duplicates[] = $name;
                continue;
            }

            $seen[$name] = true;
        }

        if ($duplicates !== []) {
            throw new RuntimeException(
                sprintf('Duplicate seeder names were found: %s', implode(', ', array_unique($duplicates)))
            );
        }

        return $seeders;
    }
}
