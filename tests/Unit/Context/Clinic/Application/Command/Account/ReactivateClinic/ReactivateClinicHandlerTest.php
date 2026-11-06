<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Application\Command\Account\ReactivateClinic;

use App\Context\Clinic\Application\Command\Account\ReactivateClinic\ReactivateClinic;
use App\Context\Clinic\Application\Command\Account\ReactivateClinic\ReactivateClinicHandler;
use App\Context\Clinic\Application\Service\ClinicAccounts;
use App\Context\Clinic\Domain\Account\ClinicAccount;
use App\Context\Clinic\Domain\Account\ClinicSuspension;
use App\Context\Clinic\Domain\Account\SuspensionReason;
use App\Context\Clinic\Domain\Event\ClinicActivated;
use App\Context\Clinic\Domain\ValueObject\ClinicId;
use App\Context\Clinic\Domain\ValueObject\ClinicStatus;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Unit\Context\Clinic\Support\ConsoleFakes;
use App\Tests\Unit\Context\Clinic\Support\InMemoryClinicAccounts;
use App\Tests\Unit\Context\Clinic\Support\InMemoryClinics;
use PHPUnit\Framework\TestCase;

final class ReactivateClinicHandlerTest extends TestCase
{
    private const string CLINIC = '01960000-0000-7000-8000-00000000c6a1';

    public function testTheUsersOfTheClinicGetTheirAccessBack(): void
    {
        $clinics  = new InMemoryClinics()->add(InMemoryClinics::clinic(self::CLINIC, ClinicStatus::SUSPENDED));
        $accounts = new InMemoryClinicAccounts();
        $account  = ClinicAccount::defaultFor(ClinicId::fromString(self::CLINIC), new \DateTimeImmutable('2026-09-01'));
        $account->recordSuspension(ClinicSuspension::of(SuspensionReason::UNPAID, '', new \DateTimeImmutable('2026-10-01'), null, 'Kiveto'));
        $accounts->accounts[self::CLINIC] = $account;
        $fakes                            = new ConsoleFakes();
        $clock                            = new FrozenClock(new \DateTimeImmutable('2026-11-06 09:00:00', new \DateTimeZone('UTC')));

        new ReactivateClinicHandler(new ClinicAccounts($clinics, $accounts), $clinics, $accounts, $clock, $fakes->publisher())(new ReactivateClinic(self::CLINIC));

        self::assertSame([[self::CLINIC], ClinicStatus::ACTIVE], [$clinics->saved, $clinics->clinics[self::CLINIC]->status()]);
        self::assertSame([[self::CLINIC], null, $clock->now()], [$accounts->saved, $account->suspension(), $account->updatedAt()]);
        self::assertCount(1, $fakes->events);
        self::assertInstanceOf(ClinicActivated::class, $fakes->events[0]);
    }
}
