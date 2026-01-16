<?php

declare(strict_types=1);

namespace App\Tests\Unit\AccessControl\Application\Command\ChangeClinicMembershipValidityWindow;

use App\AccessControl\Application\Command\ChangeClinicMembershipValidityWindow\ChangeClinicMembershipValidityWindow;
use PHPUnit\Framework\TestCase;

final class ChangeClinicMembershipValidityWindowTest extends TestCase
{
    public function testCommandConstructionWithValidUntil(): void
    {
        $validFrom  = new \DateTimeImmutable('2024-01-01');
        $validUntil = new \DateTimeImmutable('2025-01-01');

        $command = new ChangeClinicMembershipValidityWindow(
            membershipId: '11111111-1111-1111-1111-111111111111',
            validFrom: $validFrom,
            validUntil: $validUntil,
        );

        self::assertSame('11111111-1111-1111-1111-111111111111', $command->membershipId);
        self::assertSame($validFrom, $command->validFrom);
        self::assertSame($validUntil, $command->validUntil);
    }

    public function testCommandConstructionWithNullValidUntil(): void
    {
        $validFrom = new \DateTimeImmutable('2024-01-01');

        $command = new ChangeClinicMembershipValidityWindow(
            membershipId: '22222222-2222-2222-2222-222222222222',
            validFrom: $validFrom,
            validUntil: null,
        );

        self::assertSame('22222222-2222-2222-2222-222222222222', $command->membershipId);
        self::assertSame($validFrom, $command->validFrom);
        self::assertNull($command->validUntil);
    }

    public function testCommandIsReadonly(): void
    {
        $command = new ChangeClinicMembershipValidityWindow(
            membershipId: '33333333-3333-3333-3333-333333333333',
            validFrom: new \DateTimeImmutable('2024-01-01'),
            validUntil: new \DateTimeImmutable('2025-12-31'),
        );

        self::assertSame('33333333-3333-3333-3333-333333333333', $command->membershipId);
    }
}
