<?php

declare(strict_types=1);

namespace App\Tests\Unit\AccessControl\Application\Command\EnableClinicMembership;

use App\AccessControl\Application\Command\EnableClinicMembership\EnableClinicMembership;
use PHPUnit\Framework\TestCase;

final class EnableClinicMembershipTest extends TestCase
{
    public function testCommandConstruction(): void
    {
        $command = new EnableClinicMembership(
            membershipId: '11111111-1111-1111-1111-111111111111',
        );

        self::assertSame('11111111-1111-1111-1111-111111111111', $command->membershipId);
    }

    public function testCommandIsReadonly(): void
    {
        $command = new EnableClinicMembership(
            membershipId: '22222222-2222-2222-2222-222222222222',
        );

        self::assertSame('22222222-2222-2222-2222-222222222222', $command->membershipId);
    }
}
