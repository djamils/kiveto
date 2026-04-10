<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\AccessControl\Domain\ValueObject;

use App\System\AccessControl\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class TenantIdTest extends TestCase
{
    public function testFromStringAndToString(): void
    {
        $id = TenantId::fromString('01912345-6789-7abc-8def-0123456789ab');

        self::assertSame('01912345-6789-7abc-8def-0123456789ab', $id->toString());
        self::assertSame('01912345-6789-7abc-8def-0123456789ab', (string) $id);
    }

    public function testEquals(): void
    {
        $a = TenantId::fromString('01912345-6789-7abc-8def-0123456789ab');
        $b = TenantId::fromString('01912345-6789-7abc-8def-0123456789ab');
        $c = TenantId::fromString('01912345-6789-7abc-8def-0123456789ac');

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }

    public function testEmptyThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        TenantId::fromString('');
    }
}
