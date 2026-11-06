<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Application\Command\Account\OpenClinicAccount;

use App\Context\Clinic\Application\Command\Account\OpenClinicAccount\OpenClinicAccount;
use App\Context\Clinic\Application\Command\Account\OpenClinicAccount\OpenClinicAccountHandler;
use App\Context\Clinic\Domain\Account\ClinicPlan;
use App\Context\Clinic\Domain\Account\ParallelSoftware;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Unit\Context\Clinic\Support\InMemoryClinicAccounts;
use App\Tests\Unit\Context\Clinic\Support\InMemoryClinics;
use PHPUnit\Framework\TestCase;

final class OpenClinicAccountHandlerTest extends TestCase
{
    private const string CLINIC = '01960000-0000-7000-8000-00000000c3a1';

    private InMemoryClinicAccounts $accounts;
    private FrozenClock $clock;

    protected function setUp(): void
    {
        $this->accounts = new InMemoryClinicAccounts();
        $this->clock    = new FrozenClock(new \DateTimeImmutable('2026-11-06 09:00:00', new \DateTimeZone('UTC')));
    }

    public function testThePilotOfANewClinicIsOpened(): void
    {
        $this->open(new OpenClinicAccount(self::CLINIC, 'pilot', '2027-01-31', 'drveto'));

        $account = $this->accounts->accounts[self::CLINIC] ?? null;
        self::assertNotNull($account);
        self::assertSame([ClinicPlan::PILOT, '2027-01-31', ParallelSoftware::DR_VETO, $this->clock->now(), $this->clock->now()], [
            $account->plan(), $account->pilotEndsOn()?->format('Y-m-d'), $account->parallelSoftware(), $account->createdAt(), $account->updatedAt(),
        ]);
    }

    public function testAPaidClinicNeedsNeitherEndNorSoftware(): void
    {
        $this->open(new OpenClinicAccount(self::CLINIC, 'paid'));

        $account = $this->accounts->accounts[self::CLINIC] ?? null;
        self::assertNotNull($account);
        self::assertSame([ClinicPlan::PAID, null, null], [$account->plan(), $account->pilotEndsOn(), $account->parallelSoftware()]);
    }

    public function testAnUnknownClinicGetsNoAccount(): void
    {
        try {
            $this->open(new OpenClinicAccount('01960000-0000-7000-8000-00000000c3ff', 'pilot'));
            self::fail('An unknown clinic was expected to be refused.');
        } catch (\RuntimeException $e) {
            self::assertSame('Clinic with ID "01960000-0000-7000-8000-00000000c3ff" not found.', $e->getMessage());
        }

        self::assertSame([], $this->accounts->saved);
    }

    private function open(OpenClinicAccount $command): void
    {
        new OpenClinicAccountHandler(new InMemoryClinics()->add(InMemoryClinics::clinic(self::CLINIC)), $this->accounts, $this->clock)($command);
    }
}
