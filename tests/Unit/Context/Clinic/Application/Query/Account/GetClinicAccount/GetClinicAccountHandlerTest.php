<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Application\Query\Account\GetClinicAccount;

use App\Context\Clinic\Application\Query\Account\GetClinicAccount\ClinicAccountView;
use App\Context\Clinic\Application\Query\Account\GetClinicAccount\GetClinicAccount;
use App\Context\Clinic\Application\Query\Account\GetClinicAccount\GetClinicAccountHandler;
use App\Context\Clinic\Application\Service\ClinicAccounts;
use App\Context\Clinic\Domain\Account\ClinicAccount;
use App\Context\Clinic\Domain\Account\ClinicPlan;
use App\Context\Clinic\Domain\Account\ClinicSuspension;
use App\Context\Clinic\Domain\Account\ParallelSoftware;
use App\Context\Clinic\Domain\Account\SuspensionReason;
use App\Context\Clinic\Domain\ValueObject\ClinicId;
use App\Tests\Unit\Context\Clinic\Support\InMemoryClinicAccounts;
use App\Tests\Unit\Context\Clinic\Support\InMemoryClinics;
use PHPUnit\Framework\TestCase;

final class GetClinicAccountHandlerTest extends TestCase
{
    private const string CLINIC = '01960000-0000-7000-8000-00000000c8a1';

    public function testASuspendedClinicShowsItsAccountWithLabels(): void
    {
        $accounts    = new InMemoryClinicAccounts();
        $pilotEnd    = new \DateTimeImmutable('2027-01-31 00:00:00', new \DateTimeZone('UTC'));
        $onboardedAt = new \DateTimeImmutable('2026-10-01 08:00:00', new \DateTimeZone('UTC'));
        $suspendedAt = new \DateTimeImmutable('2026-11-06 09:00:00', new \DateTimeZone('UTC'));
        $account     = ClinicAccount::open(ClinicId::fromString(self::CLINIC), ClinicPlan::PILOT, $pilotEnd, ParallelSoftware::DR_VETO, new \DateTimeImmutable('2026-09-01'));
        $account->revise(ClinicPlan::PILOT, $pilotEnd, ParallelSoftware::DR_VETO, 'Rappeler', $onboardedAt);
        $account->markOnboarded($onboardedAt);
        $account->recordSuspension(ClinicSuspension::of(SuspensionReason::END_OF_DEMO, 'Démo terminée', $suspendedAt, null, 'Djamil'));
        $accounts->save($account);

        $view = $this->ask($accounts);

        self::assertSame(
            [self::CLINIC, 'pilot', 'Pilote (gratuit)', '2027-01-31', 'drveto', 'dr.veto', 'Rappeler', $onboardedAt, 'end_of_demo', 'Fin de démo', 'Démo terminée', $suspendedAt, 'Djamil'],
            self::fields($view),
        );
    }

    public function testAClinicCreatedBeforeTheConsoleShowsTheDefaultAccount(): void
    {
        self::assertSame(
            [self::CLINIC, 'pilot', 'Pilote (gratuit)', null, null, null, '', null, null, null, null, null, null],
            self::fields($this->ask(new InMemoryClinicAccounts())),
        );
    }

    /**
     * @return list<mixed>
     */
    private static function fields(ClinicAccountView $view): array
    {
        return [
            $view->clinicId, $view->plan, $view->planLabel, $view->pilotEndsOn?->format('Y-m-d'), $view->parallelSoftware, $view->parallelSoftwareLabel,
            $view->internalNote, $view->onboardedAt, $view->suspensionReason, $view->suspensionReasonLabel, $view->suspensionComment,
            $view->suspendedAt, $view->suspendedByName,
        ];
    }

    private function ask(InMemoryClinicAccounts $accounts): ClinicAccountView
    {
        $clinics = new InMemoryClinics()->add(InMemoryClinics::clinic(self::CLINIC));

        return new GetClinicAccountHandler(new ClinicAccounts($clinics, $accounts))(new GetClinicAccount(self::CLINIC));
    }
}
