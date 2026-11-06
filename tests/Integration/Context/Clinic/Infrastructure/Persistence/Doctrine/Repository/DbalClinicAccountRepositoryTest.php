<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Clinic\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Clinic\Domain\Account\ClinicAccount;
use App\Context\Clinic\Domain\Account\ClinicAccountRepositoryInterface;
use App\Context\Clinic\Domain\Account\ClinicPlan;
use App\Context\Clinic\Domain\Account\ClinicSuspension;
use App\Context\Clinic\Domain\Account\ParallelSoftware;
use App\Context\Clinic\Domain\Account\SuspensionReason;
use App\Context\Clinic\Domain\ValueObject\ClinicId;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DbalClinicAccountRepositoryTest extends KernelTestCase
{
    private const string CLINIC = '01960000-0000-7000-8000-00000000e1a1';
    private const string OTHER  = '01960000-0000-7000-8000-00000000e1a2';
    private const string ACTOR  = '01960000-0000-7000-8000-00000000e1b1';

    public function testAnOpenedAccountIsReadBackWholly(): void
    {
        $accounts = $this->accounts();
        // A Paris time: the dates are stored in UTC and read back as UTC.
        $at = new \DateTimeImmutable('2026-11-06 10:00:00', new \DateTimeZone('Europe/Paris'));
        $accounts->save(ClinicAccount::open(ClinicId::fromString(self::CLINIC), ClinicPlan::PILOT, new \DateTimeImmutable('2027-01-31'), ParallelSoftware::VETUP, $at));

        self::assertNull($accounts->findByClinicId(ClinicId::fromString(self::OTHER)));

        $found = $accounts->findByClinicId(ClinicId::fromString(self::CLINIC));
        self::assertNotNull($found);
        self::assertSame([self::CLINIC, ClinicPlan::PILOT, '2027-01-31 00:00:00', ParallelSoftware::VETUP, '', null, null, '2026-11-06 09:00:00', '2026-11-06 09:00:00', 'UTC'], [
            $found->clinicId()->toString(), $found->plan(), $found->pilotEndsOn()?->format('Y-m-d H:i:s'), $found->parallelSoftware(), $found->internalNote(),
            $found->onboardedAt(), $found->suspension(), $found->createdAt()->format('Y-m-d H:i:s'), $found->updatedAt()->format('Y-m-d H:i:s'),
            $found->createdAt()->getTimezone()->getName(),
        ]);
    }

    public function testARevisedOnboardedAndSuspendedAccountIsUpdated(): void
    {
        $accounts = $this->accounts();
        $created  = new \DateTimeImmutable('2026-09-01 08:00:00', new \DateTimeZone('UTC'));
        $account  = ClinicAccount::open(ClinicId::fromString(self::CLINIC), ClinicPlan::PILOT, new \DateTimeImmutable('2027-01-31'), ParallelSoftware::VETUP, $created);
        $accounts->save($account);

        $account->revise(ClinicPlan::PAID, null, ParallelSoftware::EPIVET, 'Passée payante en novembre.', new \DateTimeImmutable('2026-11-01 08:00:00', new \DateTimeZone('UTC')));
        $account->markOnboarded(new \DateTimeImmutable('2026-11-02 08:00:00', new \DateTimeZone('UTC')));
        $account->recordSuspension(ClinicSuspension::of(SuspensionReason::UNPAID, 'Relance sans réponse', new \DateTimeImmutable('2026-11-06 10:00:00', new \DateTimeZone('Europe/Paris')), self::ACTOR, 'Djamil Soidiki'));
        $accounts->save($account);

        $found = $accounts->findByClinicId(ClinicId::fromString(self::CLINIC));
        self::assertNotNull($found);
        self::assertSame([ClinicPlan::PAID, null, ParallelSoftware::EPIVET, 'Passée payante en novembre.', '2026-11-02 08:00:00', '2026-09-01 08:00:00', '2026-11-06 09:00:00'], [
            $found->plan(), $found->pilotEndsOn(), $found->parallelSoftware(), $found->internalNote(), $found->onboardedAt()?->format('Y-m-d H:i:s'),
            $found->createdAt()->format('Y-m-d H:i:s'), $found->updatedAt()->format('Y-m-d H:i:s'),
        ]);
        $suspension = $found->suspension();
        self::assertNotNull($suspension);
        self::assertSame([SuspensionReason::UNPAID, 'Relance sans réponse', '2026-11-06 09:00:00', 'UTC', self::ACTOR, 'Djamil Soidiki'], [
            $suspension->reason(), $suspension->comment(), $suspension->suspendedAt()->format('Y-m-d H:i:s'), $suspension->suspendedAt()->getTimezone()->getName(),
            $suspension->actorId(), $suspension->actorName(),
        ]);
    }

    public function testASuspensionWithoutActorIdIsReadBackAndClearedOnReactivation(): void
    {
        $accounts = $this->accounts();
        $account  = ClinicAccount::defaultFor(ClinicId::fromString(self::CLINIC), new \DateTimeImmutable('2026-09-01 08:00:00', new \DateTimeZone('UTC')));
        $account->recordSuspension(ClinicSuspension::of(SuspensionReason::END_OF_DEMO, '', new \DateTimeImmutable('2026-11-06 09:00:00', new \DateTimeZone('UTC')), null, 'Kiveto'));
        $accounts->save($account);

        $suspension = $accounts->findByClinicId(ClinicId::fromString(self::CLINIC))?->suspension();
        self::assertNotNull($suspension);
        self::assertSame([SuspensionReason::END_OF_DEMO, '', null, 'Kiveto'], [$suspension->reason(), $suspension->comment(), $suspension->actorId(), $suspension->actorName()]);

        $account->clearSuspension(new \DateTimeImmutable('2026-11-07 09:00:00', new \DateTimeZone('UTC')));
        $accounts->save($account);

        $found = $accounts->findByClinicId(ClinicId::fromString(self::CLINIC));
        self::assertNull($found?->suspension());
        self::assertSame([ClinicPlan::PILOT, null, null, '2026-11-07 09:00:00'], [$found?->plan(), $found?->pilotEndsOn(), $found?->parallelSoftware(), $found?->updatedAt()->format('Y-m-d H:i:s')]);
    }

    private function accounts(): ClinicAccountRepositoryInterface
    {
        $repository = self::getContainer()->get(ClinicAccountRepositoryInterface::class);
        \assert($repository instanceof ClinicAccountRepositoryInterface);

        return $repository;
    }
}
