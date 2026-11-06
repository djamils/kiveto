<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Domain\Account;

use App\Context\Clinic\Domain\Account\ClinicAccount;
use App\Context\Clinic\Domain\Account\ClinicPlan;
use App\Context\Clinic\Domain\Account\ClinicSuspension;
use App\Context\Clinic\Domain\Account\ParallelSoftware;
use App\Context\Clinic\Domain\Account\SuspensionReason;
use App\Context\Clinic\Domain\ValueObject\ClinicId;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ClinicAccountTest extends TestCase
{
    private const string CLINIC = '01960000-0000-7000-8000-00000000c0a1';

    public function testAnOpenedPilotEndsAtMidnightOfItsLastDay(): void
    {
        $at      = new \DateTimeImmutable('2026-11-06 09:00:00', new \DateTimeZone('UTC'));
        $account = ClinicAccount::open(ClinicId::fromString(self::CLINIC), ClinicPlan::PILOT, new \DateTimeImmutable('2027-01-31 17:45:00', new \DateTimeZone('UTC')), ParallelSoftware::VETUP, $at);

        self::assertSame([self::CLINIC, ClinicPlan::PILOT, '2027-01-31 00:00:00', ParallelSoftware::VETUP, '', null, null, $at, $at], [
            $account->clinicId()->toString(), $account->plan(), $account->pilotEndsOn()?->format('Y-m-d H:i:s'), $account->parallelSoftware(),
            $account->internalNote(), $account->onboardedAt(), $account->suspension(), $account->createdAt(), $account->updatedAt(),
        ]);
    }

    #[DataProvider('provideOnlyAPilotEndsCases')]
    public function testOnlyAPilotEnds(ClinicPlan $plan): void
    {
        $account = ClinicAccount::open(ClinicId::fromString(self::CLINIC), $plan, new \DateTimeImmutable('2027-01-31'), null, new \DateTimeImmutable());

        self::assertNull($account->pilotEndsOn());
        self::assertNull($account->parallelSoftware());
    }

    /**
     * @return iterable<string, array{ClinicPlan}>
     */
    public static function provideOnlyAPilotEndsCases(): iterable
    {
        yield 'paid' => [ClinicPlan::PAID];

        yield 'internal' => [ClinicPlan::INTERNAL];
    }

    public function testAClinicCreatedBeforeTheConsoleIsAPilotWithoutEnd(): void
    {
        $createdAt = new \DateTimeImmutable('2026-03-01 08:00:00', new \DateTimeZone('UTC'));
        $account   = ClinicAccount::defaultFor(ClinicId::fromString(self::CLINIC), $createdAt);

        self::assertSame([ClinicPlan::PILOT, null, null, '', null, null, $createdAt, $createdAt], [
            $account->plan(), $account->pilotEndsOn(), $account->parallelSoftware(), $account->internalNote(),
            $account->onboardedAt(), $account->suspension(), $account->createdAt(), $account->updatedAt(),
        ]);
    }

    public function testARevisionReplacesThePlanTheSoftwareAndTheNote(): void
    {
        $createdAt = new \DateTimeImmutable('2026-03-01 08:00:00', new \DateTimeZone('UTC'));
        $at        = new \DateTimeImmutable('2026-11-06 09:00:00', new \DateTimeZone('UTC'));
        $account   = ClinicAccount::open(ClinicId::fromString(self::CLINIC), ClinicPlan::PILOT, new \DateTimeImmutable('2027-01-31'), ParallelSoftware::VETUP, $createdAt);

        $account->revise(ClinicPlan::PILOT, new \DateTimeImmutable('2027-03-31 12:00:00'), ParallelSoftware::OTHER, "  Rappeler le Dr Martin.\n ", $at);
        self::assertSame([ClinicPlan::PILOT, '2027-03-31 00:00:00', ParallelSoftware::OTHER, 'Rappeler le Dr Martin.', $createdAt, $at], [
            $account->plan(), $account->pilotEndsOn()?->format('Y-m-d H:i:s'), $account->parallelSoftware(), $account->internalNote(), $account->createdAt(), $account->updatedAt(),
        ]);

        $account->revise(ClinicPlan::PAID, new \DateTimeImmutable('2027-03-31'), null, str_repeat('é', 2100), $at);
        self::assertSame([ClinicPlan::PAID, null, null], [$account->plan(), $account->pilotEndsOn(), $account->parallelSoftware()]);
        self::assertSame(str_repeat('é', 2000), $account->internalNote(), 'the note is cut at 2000 characters');
    }

    public function testAClinicIsOnboardedOnceAndForGood(): void
    {
        $account = ClinicAccount::defaultFor(ClinicId::fromString(self::CLINIC), new \DateTimeImmutable('2026-03-01 08:00:00'));
        $first   = new \DateTimeImmutable('2026-11-06 09:00:00', new \DateTimeZone('UTC'));

        self::assertTrue($account->markOnboarded($first));
        self::assertSame([$first, $first], [$account->onboardedAt(), $account->updatedAt()]);

        self::assertFalse($account->markOnboarded($first->modify('+1 day')));
        self::assertSame([$first, $first], [$account->onboardedAt(), $account->updatedAt()], 'the first date is kept');
    }

    public function testASuspensionIsKeptUntilTheClinicIsReactivated(): void
    {
        $account    = ClinicAccount::defaultFor(ClinicId::fromString(self::CLINIC), new \DateTimeImmutable('2026-03-01 08:00:00'));
        $at         = new \DateTimeImmutable('2026-11-06 09:00:00', new \DateTimeZone('UTC'));
        $suspension = ClinicSuspension::of(SuspensionReason::END_OF_DEMO, 'Démo terminée', $at, null, 'Djamil');

        $account->recordSuspension($suspension);
        self::assertSame([$suspension, $at], [$account->suspension(), $account->updatedAt()]);

        $later = $at->modify('+2 days');
        $account->clearSuspension($later);
        self::assertSame([null, $later], [$account->suspension(), $account->updatedAt()]);
    }

    public function testAReconstitutedAccountKeepsEveryField(): void
    {
        $suspension = ClinicSuspension::of(SuspensionReason::UNPAID, '', new \DateTimeImmutable('2026-10-01'), null, 'Kiveto');
        $account    = ClinicAccount::reconstitute(
            ClinicId::fromString(self::CLINIC),
            ClinicPlan::PILOT,
            new \DateTimeImmutable('2027-01-31 15:00:00'),
            ParallelSoftware::EPIVET,
            'Note',
            new \DateTimeImmutable('2026-09-15'),
            $suspension,
            new \DateTimeImmutable('2026-03-01'),
            new \DateTimeImmutable('2026-10-01'),
        );

        self::assertSame(['15:00', ParallelSoftware::EPIVET, 'Note', '2026-09-15', $suspension], [
            $account->pilotEndsOn()?->format('H:i'), $account->parallelSoftware(), $account->internalNote(), $account->onboardedAt()?->format('Y-m-d'), $account->suspension(),
        ]);
    }
}
