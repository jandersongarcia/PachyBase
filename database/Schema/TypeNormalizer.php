<?php

declare(strict_types=1);

namespace PachyBase\Database\Schema;

final class TypeNormalizer
{
    public function normalize(string $driver, string $nativeType, ?string $fullType = null): string
    {
        $driver = strtolower($driver);
        $nativeType = strtolower($nativeType);
        $fullType = strtolower((string) ($fullType ?? $nativeType));

        return match ($driver) {
            'mysql' => $this->normalizeMySql($nativeType, $fullType),
            'pgsql' => $this->normalizePostgres($nativeType, $fullType),
            default => $nativeType,
        };
    }

    private function normalizeMySql(string $nativeType, string $fullType): string
    {
        return match (true) {
            $nativeType === 'tinyint' && $fullType === 'tinyint(1)' => 'boolean',
            in_array($nativeType, ['bool', 'boolean', 'bit'], true) => 'boolean',
            in_array($nativeType, ['tinyint', 'smallint', 'mediumint', 'int', 'integer'], true) => 'integer',
            $nativeType === 'bigint' => 'bigint',
            in_array($nativeType, ['decimal', 'numeric'], true) => 'decimal',
            in_array($nativeType, ['float', 'double', 'real'], true) => 'float',
            in_array($nativeType, ['char', 'varchar'], true) => 'string',
            str_contains($nativeType, 'text') => 'text',
            in_array($nativeType, ['json'], true) => 'json',
            in_array($nativeType, ['date'], true) => 'date',
            in_array($nativeType, ['time'], true) => 'time',
            in_array($nativeType, ['datetime', 'timestamp'], true) => 'datetime',
            in_array($nativeType, ['binary', 'varbinary', 'blob', 'tinyblob', 'mediumblob', 'longblob'], true) => 'binary',
            in_array($nativeType, ['enum', 'set'], true) => 'enum',
            default => $nativeType,
        };
    }

    private function normalizePostgres(string $nativeType, string $fullType): string
    {
        return match (true) {
            in_array($nativeType, ['bool', 'boolean'], true) => 'boolean',
            in_array($nativeType, ['int2', 'int4', 'smallint', 'integer'], true) => 'integer',
            in_array($nativeType, ['int8', 'bigint'], true) => 'bigint',
            in_array($nativeType, ['numeric', 'decimal'], true) => 'decimal',
            in_array($nativeType, ['float4', 'float8', 'real', 'double precision'], true) => 'float',
            in_array($nativeType, ['varchar', 'bpchar', 'char', 'character', 'character varying'], true) => 'string',
            in_array($nativeType, ['text'], true) => 'text',
            in_array($nativeType, ['json', 'jsonb'], true) => 'json',
            in_array($nativeType, ['date'], true) => 'date',
            in_array($nativeType, ['time', 'timetz'], true) => 'time',
            in_array($nativeType, ['timestamp', 'timestamptz'], true) || str_contains($fullType, 'timestamp') => 'datetime',
            in_array($nativeType, ['uuid'], true) => 'uuid',
            in_array($nativeType, ['bytea'], true) => 'binary',
            default => $nativeType,
        };
    }
}
