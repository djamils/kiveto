<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Scheduling\Infrastructure\Adapter\Clinic;

use App\Context\Scheduling\Application\Port\MembershipEligibilityCheckerInterface;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\UserId;
use App\Fixtures\Context\Clinic\Factory\ClinicMembershipEntityFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

final class DbalMembershipEligibilityCheckerTest extends KernelTestCase
{
    use Factories;

    private const string CLINIC_ID = '12345678-9abc-def0-1234-56789abcdef0';
    private const string USER_ID   = '01234567-89ab-cdef-0123-456789abcdef';

    public function testIsUserEligibleReturnsTrueForActiveVeterinaryMembership(): void
    {
        ClinicMembershipEntityFactory::new()
            ->withClinicId(self::CLINIC_ID)
            ->withUserId(self::USER_ID)
            ->asVeterinary()
            ->asEmployee()
            ->create(['validFrom' => new \DateTimeImmutable('2026-01-01')])
        ;

        self::assertTrue($this->checker()->isUserEligibleForClinicAt(
            UserId::fromString(self::USER_ID),
            ClinicId::fromString(self::CLINIC_ID),
            new \DateTimeImmutable('2026-04-10 12:00:00'),
            ['VETERINARY', 'VETERINARY_ASSISTANT'],
        ));
    }

    public function testIsUserEligibleReturnsFalseWhenRoleNotAllowed(): void
    {
        ClinicMembershipEntityFactory::new()
            ->withClinicId(self::CLINIC_ID)
            ->withUserId(self::USER_ID)
            ->asManager()
            ->asEmployee()
            ->create(['validFrom' => new \DateTimeImmutable('2026-01-01')])
        ;

        self::assertFalse($this->checker()->isUserEligibleForClinicAt(
            UserId::fromString(self::USER_ID),
            ClinicId::fromString(self::CLINIC_ID),
            new \DateTimeImmutable('2026-04-10 12:00:00'),
            ['VETERINARY'],
        ));
    }

    public function testIsUserEligibleReturnsFalseForExpiredMembership(): void
    {
        ClinicMembershipEntityFactory::new()
            ->withClinicId(self::CLINIC_ID)
            ->withUserId(self::USER_ID)
            ->asVeterinary()
            ->asContractor(new \DateTimeImmutable('2026-03-01'))
            ->create(['validFrom' => new \DateTimeImmutable('2026-01-01')])
        ;

        self::assertFalse($this->checker()->isUserEligibleForClinicAt(
            UserId::fromString(self::USER_ID),
            ClinicId::fromString(self::CLINIC_ID),
            new \DateTimeImmutable('2026-04-10 12:00:00'),
            ['VETERINARY'],
        ));
    }

    public function testIsUserEligibleReturnsFalseForDisabledMembership(): void
    {
        ClinicMembershipEntityFactory::new()
            ->withClinicId(self::CLINIC_ID)
            ->withUserId(self::USER_ID)
            ->asVeterinary()
            ->asEmployee()
            ->disabled()
            ->create(['validFrom' => new \DateTimeImmutable('2026-01-01')])
        ;

        self::assertFalse($this->checker()->isUserEligibleForClinicAt(
            UserId::fromString(self::USER_ID),
            ClinicId::fromString(self::CLINIC_ID),
            new \DateTimeImmutable('2026-04-10 12:00:00'),
            ['VETERINARY'],
        ));
    }

    public function testListEligiblePractitionerUsersForClinic(): void
    {
        $vetUserId     = '01234567-89ab-cdef-0123-456789abcdef';
        $managerUserId = '02345678-9abc-def0-1234-56789abcdef0';

        ClinicMembershipEntityFactory::new()
            ->withClinicId(self::CLINIC_ID)
            ->withUserId($vetUserId)
            ->asVeterinary()
            ->asEmployee()
            ->create(['validFrom' => new \DateTimeImmutable('2026-01-01')])
        ;

        ClinicMembershipEntityFactory::new()
            ->withClinicId(self::CLINIC_ID)
            ->withUserId($managerUserId)
            ->asManager()
            ->asEmployee()
            ->create(['validFrom' => new \DateTimeImmutable('2026-01-01')])
        ;

        $practitioners = $this->checker()->listEligiblePractitionerUsersForClinic(
            ClinicId::fromString(self::CLINIC_ID),
            new \DateTimeImmutable('2026-04-10 12:00:00'),
            ['VETERINARY'],
        );

        self::assertCount(1, $practitioners);
        self::assertSame($vetUserId, $practitioners[0]['userId']);
        self::assertNull($practitioners[0]['displayName']);
    }

    private function checker(): MembershipEligibilityCheckerInterface
    {
        $checker = self::getContainer()->get(MembershipEligibilityCheckerInterface::class);
        \assert($checker instanceof MembershipEligibilityCheckerInterface);

        return $checker;
    }
}
