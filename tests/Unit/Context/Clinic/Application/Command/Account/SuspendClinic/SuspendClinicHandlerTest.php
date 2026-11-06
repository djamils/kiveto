<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Application\Command\Account\SuspendClinic;

use App\Context\Clinic\Application\Command\Account\SuspendClinic\SuspendClinic;
use App\Context\Clinic\Application\Command\Account\SuspendClinic\SuspendClinicHandler;
use App\Context\Clinic\Application\Service\ClinicAccounts;
use App\Context\Clinic\Domain\Account\SuspensionReason;
use App\Context\Clinic\Domain\Event\ClinicSuspended;
use App\Context\Clinic\Domain\ValueObject\ClinicStatus;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Unit\Context\Clinic\Support\ConsoleFakes;
use App\Tests\Unit\Context\Clinic\Support\InMemoryClinicAccounts;
use App\Tests\Unit\Context\Clinic\Support\InMemoryClinics;
use PHPUnit\Framework\TestCase;

final class SuspendClinicHandlerTest extends TestCase
{
    private const string CLINIC = '01960000-0000-7000-8000-00000000c5a1';
    private const string ACTOR  = '01960000-0000-7000-8000-00000000c5b1';

    public function testTheClinicIsSuspendedAtOnceWithItsReason(): void
    {
        $clinics  = new InMemoryClinics()->add(InMemoryClinics::clinic(self::CLINIC));
        $accounts = new InMemoryClinicAccounts();
        $fakes    = new ConsoleFakes();
        $clock    = new FrozenClock(new \DateTimeImmutable('2026-11-06 09:00:00', new \DateTimeZone('UTC')));

        new SuspendClinicHandler(new ClinicAccounts($clinics, $accounts), $clinics, $accounts, $clock, $fakes->publisher())(
            new SuspendClinic(self::CLINIC, 'end_of_demo', ' Démo terminée ', self::ACTOR, 'Djamil Soidiki'),
        );

        self::assertSame([[self::CLINIC], ClinicStatus::SUSPENDED], [$clinics->saved, $clinics->clinics[self::CLINIC]->status()]);
        $suspension = $accounts->accounts[self::CLINIC]->suspension();
        self::assertNotNull($suspension);
        self::assertSame([SuspensionReason::END_OF_DEMO, 'Démo terminée', $clock->now(), self::ACTOR, 'Djamil Soidiki'], [
            $suspension->reason(), $suspension->comment(), $suspension->suspendedAt(), $suspension->actorId(), $suspension->actorName(),
        ]);
        self::assertSame([self::CLINIC], $accounts->saved);
        self::assertCount(1, $fakes->events);
        self::assertInstanceOf(ClinicSuspended::class, $fakes->events[0]);
    }
}
