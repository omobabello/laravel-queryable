<?php

declare(strict_types=1);

namespace Omoba\LaravelQueryable\Tests\Unit;

use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Omoba\LaravelQueryable\Support\DatabaseDriver;
use PHPUnit\Framework\TestCase;

final class DatabaseDriverTest extends TestCase
{
    public function test_postgres_returns_ilike(): void
    {
        $this->assertSame('ilike', DatabaseDriver::likeOperator($this->builderOnDriver('pgsql')));
    }

    public function test_mysql_returns_like(): void
    {
        $this->assertSame('like', DatabaseDriver::likeOperator($this->builderOnDriver('mysql')));
    }

    public function test_sqlite_returns_like(): void
    {
        $this->assertSame('like', DatabaseDriver::likeOperator($this->builderOnDriver('sqlite')));
    }

    public function test_mariadb_returns_like(): void
    {
        $this->assertSame('like', DatabaseDriver::likeOperator($this->builderOnDriver('mariadb')));
    }

    private function builderOnDriver(string $driver): QueryBuilder
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getDriverName')->willReturn($driver);

        $builder = $this->createMock(QueryBuilder::class);
        $builder->method('getConnection')->willReturn($connection);

        return $builder;
    }
}
