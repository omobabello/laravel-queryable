<?php

declare(strict_types=1);

namespace Omoba\LaravelQueryable\Tests\Unit;

use Omoba\LaravelQueryable\Support\RelationPath;
use PHPUnit\Framework\TestCase;

final class RelationPathTest extends TestCase
{
    public function test_local_column_has_no_relation(): void
    {
        $path = RelationPath::parse('name');

        $this->assertSame('', $path->relation);
        $this->assertSame('name', $path->column);
        $this->assertFalse($path->hasRelation());
    }

    public function test_single_relation(): void
    {
        $path = RelationPath::parse('company.industry');

        $this->assertSame('company', $path->relation);
        $this->assertSame('industry', $path->column);
        $this->assertTrue($path->hasRelation());
    }

    public function test_nested_relation(): void
    {
        $path = RelationPath::parse('team.company.name');

        $this->assertSame('team.company', $path->relation);
        $this->assertSame('name', $path->column);
        $this->assertTrue($path->hasRelation());
    }
}
