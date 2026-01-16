<?php

declare(strict_types=1);

namespace App\Tests\Unit\AccessControl\Application\Query\ListClinicsForUser;

use App\AccessControl\Application\Query\ListClinicsForUser\ListClinicsForUser;
use PHPUnit\Framework\TestCase;

final class ListClinicsForUserTest extends TestCase
{
    public function testQueryConstruction(): void
    {
        $query = new ListClinicsForUser(userId: '11111111-1111-1111-1111-111111111111');

        self::assertSame('11111111-1111-1111-1111-111111111111', $query->userId);
    }

    public function testQueryIsReadonly(): void
    {
        $query = new ListClinicsForUser(userId: '22222222-2222-2222-2222-222222222222');

        self::assertSame('22222222-2222-2222-2222-222222222222', $query->userId);
    }
}
