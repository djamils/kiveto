<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Consultation\Infrastructure\Adapter\Admission;

use App\Context\Admission\Domain\ValueObject\AdmissionStatus;
use App\Context\Admission\Domain\ValueObject\ClosureReason;
use App\Context\Admission\Domain\ValueObject\IntakeChannel;
use App\Context\Admission\Domain\ValueObject\TriageLevel;
use App\Context\Consultation\Application\Port\AdmissionFactsProviderInterface;
use App\Fixtures\Context\Admission\Factory\AdmissionEntityFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

final class DbalAdmissionFactsProviderTest extends KernelTestCase
{
    use Factories;

    private const string CLINIC_ID = '01960000-0000-7000-8000-0000000000c1';
    private const string OTHER     = '01960000-0000-7000-8000-0000000000c2';

    private const string STANDARD   = '01960000-0000-7000-8000-0000000000d1';
    private const string TRIAGED    = '01960000-0000-7000-8000-0000000000d2';
    private const string BROUGHT_IN = '01960000-0000-7000-8000-0000000000d3';
    private const string CLOSED     = '01960000-0000-7000-8000-0000000000d4';
    private const string FOREIGN    = '01960000-0000-7000-8000-0000000000d5';

    private AdmissionFactsProviderInterface $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $provider = self::getContainer()->get(AdmissionFactsProviderInterface::class);
        \assert($provider instanceof AdmissionFactsProviderInterface);
        $this->provider = $provider;

        $this->admission(self::STANDARD, self::CLINIC_ID, TriageLevel::Standard, IntakeChannel::WalkIn, false);
        $this->admission(self::TRIAGED, self::CLINIC_ID, TriageLevel::VitalEmergency, IntakeChannel::WalkIn, false);
        $this->admission(self::BROUGHT_IN, self::CLINIC_ID, TriageLevel::Priority, IntakeChannel::EmergencyByThirdParty, false);
        $this->admission(self::CLOSED, self::CLINIC_ID, TriageLevel::Standard, IntakeChannel::AppointmentBooked, true);
        $this->admission(self::FOREIGN, self::OTHER, TriageLevel::Emergency, IntakeChannel::Emergency, true);
    }

    public function testTheClosedVisitsAmongSomeOfTheClinic(): void
    {
        self::assertSame([self::CLOSED], $this->provider->closedAmong(self::CLINIC_ID, [self::STANDARD, self::CLOSED, self::FOREIGN]));
        self::assertSame([], $this->provider->closedAmong(self::CLINIC_ID, []));
    }

    public function testTheEmergenciesAmongSomeOfTheClinic(): void
    {
        $ids = $this->provider->emergenciesAmong(self::CLINIC_ID, [self::STANDARD, self::TRIAGED, self::BROUGHT_IN, self::FOREIGN]);
        sort($ids);

        self::assertSame([self::TRIAGED, self::BROUGHT_IN], $ids);
        self::assertSame([], $this->provider->emergenciesAmong(self::CLINIC_ID, []));
    }

    public function testEveryEmergencyOfTheClinic(): void
    {
        $ids = $this->provider->emergencyAdmissionIds(self::CLINIC_ID);
        sort($ids);

        self::assertSame([self::TRIAGED, self::BROUGHT_IN], $ids);
    }

    private function admission(string $id, string $clinicId, TriageLevel $triage, IntakeChannel $channel, bool $closed): void
    {
        AdmissionEntityFactory::new()
            ->withId($id)
            ->withClinicId($clinicId)
            ->create([
                'triageLevel'   => $triage,
                'intakeChannel' => $channel,
                'status'        => $closed ? AdmissionStatus::Closed : AdmissionStatus::Active,
                'closureReason' => $closed ? ClosureReason::ConsultationCompleted : null,
                'closedAt'      => $closed ? new \DateTimeImmutable() : null,
            ])
        ;
    }
}
