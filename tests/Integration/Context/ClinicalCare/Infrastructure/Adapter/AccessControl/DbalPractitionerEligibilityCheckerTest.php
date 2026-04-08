<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\ClinicalCare\Infrastructure\Adapter\AccessControl;

use App\Context\ClinicalCare\Application\Port\PractitionerEligibilityCheckerInterface;
use App\Context\ClinicalCare\Domain\ValueObject\ClinicId;
use App\Context\ClinicalCare\Domain\ValueObject\UserId;
use App\Fixtures\System\AccessControl\Factory\ClinicMembershipEntityFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

final class DbalPractitionerEligibilityCheckerTest extends KernelTestCase
{
    use Factories;

    private const string CLINIC_ID = '11111111-1111-4111-8111-111111111111';
    private const string USER_ID   = '22222222-2222-4222-8222-222222222222';

    public function testEligibleVeterinaryReturnsTrue(): void
    {
        ClinicMembershipEntityFactory::new()
            ->withClinicId(self::CLINIC_ID)
            ->withUserId(self::USER_ID)
            ->asVeterinary()
            ->asEmployee()
            ->create(['validFrom' => new \DateTimeImmutable('2026-01-01')])
        ;

        $checker = self::getContainer()->get(PractitionerEligibilityCheckerInterface::class);
        \assert($checker instanceof PractitionerEligibilityCheckerInterface);

        self::assertTrue($checker->isEligibleForClinicAt(
            UserId::fromString(self::USER_ID),
            ClinicId::fromString(self::CLINIC_ID),
            new \DateTimeImmutable('2026-04-10 12:00:00'),
            ['VETERINARY'],
        ));
    }

    public function testWrongRoleReturnsFalse(): void
    {
        ClinicMembershipEntityFactory::new()
            ->withClinicId(self::CLINIC_ID)
            ->withUserId(self::USER_ID)
            ->asClinicAdmin()
            ->asEmployee()
            ->create(['validFrom' => new \DateTimeImmutable('2026-01-01')])
        ;

        $checker = self::getContainer()->get(PractitionerEligibilityCheckerInterface::class);
        \assert($checker instanceof PractitionerEligibilityCheckerInterface);

        self::assertFalse($checker->isEligibleForClinicAt(
            UserId::fromString(self::USER_ID),
            ClinicId::fromString(self::CLINIC_ID),
            new \DateTimeImmutable('2026-04-10 12:00:00'),
            ['VETERINARY'],
        ));
    }

    public function testExpiredMembershipReturnsFalse(): void
    {
        ClinicMembershipEntityFactory::new()
            ->withClinicId(self::CLINIC_ID)
            ->withUserId(self::USER_ID)
            ->asVeterinary()
            ->asContractor(new \DateTimeImmutable('2026-03-01'))
            ->create(['validFrom' => new \DateTimeImmutable('2026-01-01')])
        ;

        $checker = self::getContainer()->get(PractitionerEligibilityCheckerInterface::class);
        \assert($checker instanceof PractitionerEligibilityCheckerInterface);

        self::assertFalse($checker->isEligibleForClinicAt(
            UserId::fromString(self::USER_ID),
            ClinicId::fromString(self::CLINIC_ID),
            new \DateTimeImmutable('2026-04-10 12:00:00'),
            ['VETERINARY'],
        ));
    }
}
