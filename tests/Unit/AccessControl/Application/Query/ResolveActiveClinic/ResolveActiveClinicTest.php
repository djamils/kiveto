<?php

declare(strict_types=1);

namespace App\Tests\Unit\AccessControl\Application\Query\ResolveActiveClinic;

use App\AccessControl\Application\Query\ResolveActiveClinic\ResolveActiveClinic;
use PHPUnit\Framework\TestCase;

final class ResolveActiveClinicTest extends TestCase
{
    public function testQueryConstruction(): void
    {
        $query = new ResolveActiveClinic(userId: '11111111-1111-1111-1111-111111111111');

        self::assertSame('11111111-1111-1111-1111-111111111111', $query->userId);
    }

    public function testQueryIsReadonly(): void
    {
        $query = new ResolveActiveClinic(userId: '22222222-2222-2222-2222-222222222222');

        self::assertSame('22222222-2222-2222-2222-222222222222', $query->userId);
    }
}
