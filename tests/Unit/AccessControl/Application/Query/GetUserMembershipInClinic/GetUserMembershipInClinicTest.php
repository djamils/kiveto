<?php

declare(strict_types=1);

namespace App\Tests\Unit\AccessControl\Application\Query\GetUserMembershipInClinic;

use App\AccessControl\Application\Query\GetUserMembershipInClinic\GetUserMembershipInClinic;
use PHPUnit\Framework\TestCase;

final class GetUserMembershipInClinicTest extends TestCase
{
    public function testQueryConstruction(): void
    {
        $query = new GetUserMembershipInClinic(
            userId: '11111111-1111-1111-1111-111111111111',
            clinicId: '22222222-2222-2222-2222-222222222222',
        );

        self::assertSame('11111111-1111-1111-1111-111111111111', $query->userId);
        self::assertSame('22222222-2222-2222-2222-222222222222', $query->clinicId);
    }

    public function testQueryIsReadonly(): void
    {
        $query = new GetUserMembershipInClinic(
            userId: '33333333-3333-3333-3333-333333333333',
            clinicId: '44444444-4444-4444-4444-444444444444',
        );

        self::assertSame('33333333-3333-3333-3333-333333333333', $query->userId);
        self::assertSame('44444444-4444-4444-4444-444444444444', $query->clinicId);
    }
}
