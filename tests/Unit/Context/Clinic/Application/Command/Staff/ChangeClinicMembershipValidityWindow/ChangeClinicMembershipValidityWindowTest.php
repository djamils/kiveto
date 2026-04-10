<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Application\Command\Staff\ChangeClinicMembershipValidityWindow;

use App\Context\Clinic\Application\Command\Staff\ChangeClinicMembershipValidityWindow\ChangeClinicMembershipValidityWindow;
use PHPUnit\Framework\TestCase;

final class ChangeClinicMembershipValidityWindowTest extends TestCase
{
    public function testConstruct(): void
    {
        $validFrom  = new \DateTimeImmutable('2026-01-01');
        $validUntil = new \DateTimeImmutable('2026-12-31');

        $command = new ChangeClinicMembershipValidityWindow('membership-uuid', $validFrom, $validUntil);

        self::assertSame('membership-uuid', $command->membershipId);
        self::assertSame($validFrom, $command->validFrom);
        self::assertSame($validUntil, $command->validUntil);
    }
}
