<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Application\Command\Account\ReviseClinicAccount;

use App\Context\Clinic\Application\Command\Account\ReviseClinicAccount\ReviseClinicAccount;
use App\Context\Clinic\Application\Command\Account\ReviseClinicAccount\ReviseClinicAccountHandler;
use App\Context\Clinic\Application\Service\ClinicAccounts;
use App\Context\Clinic\Domain\Account\ClinicPlan;
use App\Context\Clinic\Domain\Account\ParallelSoftware;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Unit\Context\Clinic\Support\InMemoryClinicAccounts;
use App\Tests\Unit\Context\Clinic\Support\InMemoryClinics;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReviseClinicAccountHandlerTest extends TestCase
{
    private const string CLINIC = '01960000-0000-7000-8000-00000000c4a1';

    /**
     * A clinic created before the console has no account row yet: its
     * default account is revised and written.
     */
    public function testTheAccountIsRevisedAndSaved(): void
    {
        $accounts = new InMemoryClinicAccounts();
        $clock    = new FrozenClock(new \DateTimeImmutable('2026-11-06 09:00:00', new \DateTimeZone('UTC')));

        $this->revise($accounts, $clock, new ReviseClinicAccount(self::CLINIC, 'pilot', '2027-03-31', 'vetocom', ' Rappeler en janvier. '));

        $account = $accounts->accounts[self::CLINIC] ?? null;
        self::assertNotNull($account);
        self::assertSame([[self::CLINIC], ClinicPlan::PILOT, '2027-03-31', ParallelSoftware::VETOCOM, 'Rappeler en janvier.', $clock->now()], [
            $accounts->saved, $account->plan(), $account->pilotEndsOn()?->format('Y-m-d'), $account->parallelSoftware(), $account->internalNote(), $account->updatedAt(),
        ]);
    }

    #[DataProvider('provideNoSoftwareChosenIsNoneCases')]
    public function testNoSoftwareChosenIsNone(?string $software, ?string $pilotEndsOn): void
    {
        $accounts = new InMemoryClinicAccounts();

        $this->revise($accounts, new FrozenClock(new \DateTimeImmutable()), new ReviseClinicAccount(self::CLINIC, 'internal', $pilotEndsOn, $software, ''));

        self::assertSame([ClinicPlan::INTERNAL, null, null], [$accounts->accounts[self::CLINIC]->plan(), $accounts->accounts[self::CLINIC]->parallelSoftware(), $accounts->accounts[self::CLINIC]->pilotEndsOn()]);
    }

    /**
     * @return iterable<string, array{?string, ?string}>
     */
    public static function provideNoSoftwareChosenIsNoneCases(): iterable
    {
        yield 'empty choice' => ['', ''];

        yield 'no field' => [null, null];
    }

    private function revise(InMemoryClinicAccounts $accounts, FrozenClock $clock, ReviseClinicAccount $command): void
    {
        $clinics = new InMemoryClinics()->add(InMemoryClinics::clinic(self::CLINIC));

        new ReviseClinicAccountHandler(new ClinicAccounts($clinics, $accounts), $accounts, $clock)($command);
    }
}
