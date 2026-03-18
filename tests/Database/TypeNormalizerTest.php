<?php

declare(strict_types=1);

namespace Tests\Database;

use PachyBase\Database\Schema\TypeNormalizer;
use PHPUnit\Framework\TestCase;

class TypeNormalizerTest extends TestCase
{
    public function testNormalizesMySqlTypes(): void
    {
        $normalizer = new TypeNormalizer();

        $this->assertSame('boolean', $normalizer->normalize('mysql', 'tinyint', 'tinyint(1)'));
        $this->assertSame('string', $normalizer->normalize('mysql', 'varchar', 'varchar(255)'));
        $this->assertSame('datetime', $normalizer->normalize('mysql', 'timestamp'));
        $this->assertSame('json', $normalizer->normalize('mysql', 'json'));
    }

    public function testNormalizesPostgresTypes(): void
    {
        $normalizer = new TypeNormalizer();

        $this->assertSame('bigint', $normalizer->normalize('pgsql', 'int8'));
        $this->assertSame('datetime', $normalizer->normalize('pgsql', 'timestamptz', 'timestamp with time zone'));
        $this->assertSame('json', $normalizer->normalize('pgsql', 'jsonb'));
        $this->assertSame('uuid', $normalizer->normalize('pgsql', 'uuid'));
    }
}
