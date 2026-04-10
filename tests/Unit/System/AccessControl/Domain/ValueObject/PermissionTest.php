<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\AccessControl\Domain\ValueObject;

use App\System\AccessControl\Domain\ValueObject\Permission;
use PHPUnit\Framework\TestCase;

final class PermissionTest extends TestCase
{
    public function testFromStringAndToString(): void
    {
        $p = Permission::fromString('create_prescription');

        self::assertSame('create_prescription', $p->toString());
        self::assertSame('create_prescription', (string) $p);
    }

    public function testEquals(): void
    {
        $a = Permission::fromString('create_prescription');
        $b = Permission::fromString('create_prescription');
        $c = Permission::fromString('view_dashboard');

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }

    public function testEmptyThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Permission::fromString('');
    }

    public function testWhitespaceOnlyThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Permission::fromString('   ');
    }
}
