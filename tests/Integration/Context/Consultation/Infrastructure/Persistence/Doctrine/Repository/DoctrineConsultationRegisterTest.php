<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Consultation\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Consultation\Application\Port\ConsultationReadRepositoryInterface;
use App\Context\Consultation\Application\Query\ListConsultationRegister\ConsultationRegisterCriteria;
use App\Context\Consultation\Application\Query\ListConsultationRegister\ConsultationRegisterRow;
use App\Context\Consultation\Domain\Consultation;
use App\Context\Consultation\Domain\Repository\ConsultationRepositoryInterface;
use App\Context\Consultation\Domain\ValueObject\AdmissionId;
use App\Context\Consultation\Domain\ValueObject\ClinicId;
use App\Context\Consultation\Domain\ValueObject\ConsultationId;
use App\Context\Consultation\Domain\ValueObject\DiagnosisCertainty;
use App\Context\Consultation\Domain\ValueObject\DiagnosisSource;
use App\Context\Consultation\Domain\ValueObject\PatientId;
use App\Context\Consultation\Domain\ValueObject\PlanActionKind;
use App\Context\Consultation\Domain\ValueObject\UnitPriceMode;
use App\Context\Consultation\Domain\ValueObject\UserId;
use App\Context\Consultation\Domain\ValueObject\Vitals;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

/**
 * Covers the register of consultations of the read repository: the clinic
 * boundary, every filter, the rows and the header's counters.
 */
final class DoctrineConsultationRegisterTest extends KernelTestCase
{
    use Factories;

    private const string CLINIC_ID       = 'c1c1c1c1-c1c1-4c1c-8c1c-c1c1c1c1c1c1';
    private const string OTHER_CLINIC_ID = 'c2c2c2c2-c2c2-4c2c-8c2c-c2c2c2c2c2c2';

    private const string VET_ALICE = 'a1a1a1a1-a1a1-4a1a-8a1a-a1a1a1a1a1a1';
    private const string VET_BOB   = 'b1b1b1b1-b1b1-4b1b-8b1b-b1b1b1b1b1b1';

    private const string PATIENT_1 = 'd1d1d1d1-0000-4000-8000-000000000001';
    private const string PATIENT_2 = 'd1d1d1d1-0000-4000-8000-000000000002';

    /** Closed two days ago, Alice, surgery + diagnoses + weight. */
    private const string CLOSED = '11111111-0000-4000-8000-000000000001';
    /** Open since yesterday: to close. */
    private const string LEFT_OPEN = '11111111-0000-4000-8000-000000000002';
    /** Open today, the patient gone: to close. */
    private const string DEPARTED = '11111111-0000-4000-8000-000000000003';
    /** Open today, Bob: in progress. */
    private const string RUNNING = '11111111-0000-4000-8000-000000000004';
    /** Another clinic's, open today. */
    private const string FOREIGN = '11111111-0000-4000-8000-000000000005';

    private const string ADM_CLOSED    = 'aaaaaaaa-0000-4000-8000-000000000001';
    private const string ADM_LEFT_OPEN = 'aaaaaaaa-0000-4000-8000-000000000002';
    private const string ADM_DEPARTED  = 'aaaaaaaa-0000-4000-8000-000000000003';
    private const string ADM_RUNNING   = 'aaaaaaaa-0000-4000-8000-000000000004';
    private const string ADM_FOREIGN   = 'aaaaaaaa-0000-4000-8000-000000000005';

    private const string DAY_START = '2026-05-10 22:00:00';

    private ConsultationRepositoryInterface $writeRepository;

    private ConsultationReadRepositoryInterface $readRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $writeRepository = self::getContainer()->get(ConsultationRepositoryInterface::class);
        \assert($writeRepository instanceof ConsultationRepositoryInterface);
        $this->writeRepository = $writeRepository;

        $readRepository = self::getContainer()->get(ConsultationReadRepositoryInterface::class);
        \assert($readRepository instanceof ConsultationReadRepositoryInterface);
        $this->readRepository = $readRepository;

        $this->seed();
    }

    public function testListsTheClinicsConsultationsMostRecentFirstAndNeverAnotherClinics(): void
    {
        $result = $this->register(new ConsultationRegisterCriteria($this->dayStart()));

        self::assertSame(4, $result['total']);
        self::assertSame([self::RUNNING, self::DEPARTED, self::LEFT_OPEN, self::CLOSED], $this->ids($result['items']));
    }

    public function testAnotherClinicsConsultationCannotBeAskedForByItsId(): void
    {
        $result = $this->register(new ConsultationRegisterCriteria($this->dayStart(), consultationIds: [self::FOREIGN]));

        self::assertSame(['items' => [], 'total' => 0], $result);
    }

    public function testAnEmptyRestrictionAnswersWithoutQuerying(): void
    {
        self::assertSame(['items' => [], 'total' => 0], $this->register(new ConsultationRegisterCriteria($this->dayStart(), restrictToAdmissionIds: [])));
    }

    public function testTheStagesFollowTheDayAndTheDepartedVisits(): void
    {
        $departed = [self::ADM_DEPARTED];

        self::assertSame([self::RUNNING], $this->ids($this->register(new ConsultationRegisterCriteria($this->dayStart(), stage: 'IN_PROGRESS', departedAdmissionIds: $departed))['items']));
        self::assertSame([self::DEPARTED, self::LEFT_OPEN], $this->ids($this->register(new ConsultationRegisterCriteria($this->dayStart(), stage: 'TO_CLOSE', departedAdmissionIds: $departed))['items']));
        self::assertSame([self::CLOSED], $this->ids($this->register(new ConsultationRegisterCriteria($this->dayStart(), stage: 'CLOSED'))['items']));

        // Without departed visits, only the day decides.
        self::assertSame([self::RUNNING, self::DEPARTED], $this->ids($this->register(new ConsultationRegisterCriteria($this->dayStart(), stage: 'IN_PROGRESS'))['items']));
        self::assertSame([self::LEFT_OPEN], $this->ids($this->register(new ConsultationRegisterCriteria($this->dayStart(), stage: 'TO_CLOSE'))['items']));
    }

    public function testFiltersNarrowTheList(): void
    {
        $day = $this->dayStart();

        self::assertSame([self::RUNNING], $this->ids($this->register(new ConsultationRegisterCriteria($day, practitionerUserId: self::VET_BOB))['items']));
        self::assertSame([self::RUNNING, self::DEPARTED], $this->ids($this->register(new ConsultationRegisterCriteria($day, startedAfterUtc: $day))['items']));
        self::assertSame([self::LEFT_OPEN], $this->ids($this->register(new ConsultationRegisterCriteria($day, restrictToAdmissionIds: [self::ADM_LEFT_OPEN, self::ADM_FOREIGN]))['items']));
        self::assertSame([self::CLOSED], $this->ids($this->register(new ConsultationRegisterCriteria($day, restrictToActCodes: ['STERIL']))['items']));
        self::assertSame([self::CLOSED], $this->ids($this->register(new ConsultationRegisterCriteria($day, consultationIds: [self::CLOSED]))['items']));
    }

    public function testFreeTextSpansTheConsultationAndThePatientsOfTheCaller(): void
    {
        $day = $this->dayStart();

        self::assertSame([self::CLOSED], $this->ids($this->register(new ConsultationRegisterCriteria($day, searchTerm: 'Ovariectomie'))['items']));
        self::assertSame([self::CLOSED], $this->ids($this->register(new ConsultationRegisterCriteria($day, searchTerm: 'pyomètre'))['items']));
        self::assertSame([self::RUNNING], $this->ids($this->register(new ConsultationRegisterCriteria($day, searchTerm: 'Vaccination'))['items']));
        self::assertSame([self::LEFT_OPEN], $this->ids($this->register(new ConsultationRegisterCriteria($day, searchTerm: 'Boiterie'))['items']));
        self::assertSame([self::RUNNING, self::DEPARTED], $this->ids($this->register(new ConsultationRegisterCriteria($day, searchTerm: 'Luna', textMatchPatientIds: [self::PATIENT_2]))['items']));

        // LIKE wildcards in the term are taken literally.
        self::assertSame([], $this->ids($this->register(new ConsultationRegisterCriteria($day, searchTerm: '%'))['items']));
    }

    public function testPagination(): void
    {
        $first  = $this->register(new ConsultationRegisterCriteria($this->dayStart(), page: 1, limit: 3));
        $second = $this->register(new ConsultationRegisterCriteria($this->dayStart(), page: 2, limit: 3));

        self::assertSame(4, $first['total']);
        self::assertSame([self::RUNNING, self::DEPARTED, self::LEFT_OPEN], $this->ids($first['items']));
        self::assertSame([self::CLOSED], $this->ids($second['items']));
    }

    public function testRowsCarryWhatTheRegisterDraws(): void
    {
        $rows = [];

        foreach ($this->register(new ConsultationRegisterCriteria($this->dayStart()))['items'] as $row) {
            $rows[$row->consultationId] = $row;
        }

        $closed = $rows[self::CLOSED];
        self::assertSame(self::ADM_CLOSED, $closed->admissionId);
        self::assertSame(self::PATIENT_1, $closed->patientId);
        self::assertSame(self::VET_ALICE, $closed->practitionerUserId);
        self::assertSame('CLOSED', $closed->status);
        self::assertSame('2026-05-08 09:00:00', $closed->startedAtUtc);
        self::assertSame('2026-05-08 09:40:00', $closed->closedAtUtc);
        self::assertSame('Stérilisation', $closed->chiefComplaint);
        self::assertSame('Ovariectomie sans complication', $closed->summary);
        self::assertSame(['Stérilisation'], $closed->motifs);
        self::assertSame('Pyomètre', $closed->primaryDiagnosis);
        self::assertSame('3.200', $closed->weightKg);
        self::assertSame([['code' => 'STERIL', 'label' => 'Ovariectomie'], ['code' => null, 'label' => 'Soins']], $closed->billedItems);
        self::assertSame([], $closed->htByTaxCategory);
        self::assertSame(['veterinary.act.surgery' => 16500, '' => 1000], $closed->ttcByTaxCategory);
        self::assertSame('EUR', $closed->currency);

        // The only diagnosis counts even when not marked primary.
        self::assertSame('Arthrose', $rows[self::LEFT_OPEN]->primaryDiagnosis);

        $running = $rows[self::RUNNING];
        self::assertNull($running->primaryDiagnosis);
        self::assertNull($running->weightKg);
        self::assertSame([], $running->billedItems);
        self::assertSame([], $running->htByTaxCategory);
        self::assertSame('', $running->currency);
    }

    public function testCountersOfTheHeader(): void
    {
        $clinic = ClinicId::fromString(self::CLINIC_ID);

        self::assertSame(
            ['inProgress' => 1, 'toClose' => 2, 'today' => 2, 'recorded' => 4],
            $this->readRepository->registerCounters($clinic, $this->dayStart(), [self::ADM_DEPARTED]),
        );
        self::assertSame(
            ['inProgress' => 2, 'toClose' => 1, 'today' => 2, 'recorded' => 4],
            $this->readRepository->registerCounters($clinic, $this->dayStart(), []),
        );
        self::assertSame(
            ['inProgress' => 0, 'toClose' => 0, 'today' => 0, 'recorded' => 0],
            $this->readRepository->registerCounters(ClinicId::fromString('c3c3c3c3-c3c3-4c3c-8c3c-c3c3c3c3c3c3'), $this->dayStart(), []),
        );
    }

    public function testTheOpenConsultationsVisitsOfTheClinic(): void
    {
        $ids = $this->readRepository->openConsultationAdmissionIds(ClinicId::fromString(self::CLINIC_ID));
        sort($ids);

        self::assertSame([self::ADM_LEFT_OPEN, self::ADM_DEPARTED, self::ADM_RUNNING], $ids);
    }

    private function seed(): void
    {
        $alice = UserId::fromString(self::VET_ALICE);

        $closed = $this->start(self::CLOSED, self::CLINIC_ID, self::ADM_CLOSED, self::PATIENT_1, self::VET_ALICE, '2026-05-08 09:00:00');
        $closed->recordChiefComplaint('Stérilisation', new \DateTimeImmutable('2026-05-08 09:01:00'));
        $closed->setMotifs(['Stérilisation'], new \DateTimeImmutable('2026-05-08 09:01:00'));
        $closed->recordVitals(Vitals::create(3.2), new \DateTimeImmutable('2026-05-08 09:02:00'));
        $closed->addPlanAction(PlanActionKind::PERFORMED_ACT, 'Ovariectomie', 'STERIL', null, null, null, 1.0, 16500, 'EUR', UnitPriceMode::INCLUDING_TAX, 'veterinary.act.surgery', $alice, new \DateTimeImmutable('2026-05-08 09:03:00'));
        $closed->addPlanAction(PlanActionKind::PERFORMED_ACT, 'Soins', null, null, null, null, 1.0, 1000, 'EUR', UnitPriceMode::INCLUDING_TAX, null, $alice, new \DateTimeImmutable('2026-05-08 09:04:00'));
        $closed->addDiagnosis(null, 'Kyste ovarien', DiagnosisCertainty::POSSIBLE, null, false, DiagnosisSource::MANUAL, $alice, new \DateTimeImmutable('2026-05-08 09:05:00'));
        $closed->addDiagnosis(null, 'Pyomètre', DiagnosisCertainty::CERTAIN, null, true, DiagnosisSource::MANUAL, $alice, new \DateTimeImmutable('2026-05-08 09:06:00'));
        $closed->close($alice, 'Ovariectomie sans complication', new \DateTimeImmutable('2026-05-08 09:40:00'));
        $this->writeRepository->save($closed);

        $leftOpen = $this->start(self::LEFT_OPEN, self::CLINIC_ID, self::ADM_LEFT_OPEN, self::PATIENT_1, self::VET_ALICE, '2026-05-10 08:00:00');
        $leftOpen->recordChiefComplaint('Boiterie', new \DateTimeImmutable('2026-05-10 08:01:00'));
        $leftOpen->addDiagnosis(null, 'Arthrose', DiagnosisCertainty::PROBABLE, null, false, DiagnosisSource::MANUAL, $alice, new \DateTimeImmutable('2026-05-10 08:05:00'));
        $this->writeRepository->save($leftOpen);

        $this->start(self::DEPARTED, self::CLINIC_ID, self::ADM_DEPARTED, self::PATIENT_2, self::VET_ALICE, '2026-05-11 07:00:00');

        $running = $this->start(self::RUNNING, self::CLINIC_ID, self::ADM_RUNNING, self::PATIENT_2, self::VET_BOB, '2026-05-11 08:00:00');
        $running->setMotifs(['Vaccination'], new \DateTimeImmutable('2026-05-11 08:01:00'));
        $this->writeRepository->save($running);

        $this->start(self::FOREIGN, self::OTHER_CLINIC_ID, self::ADM_FOREIGN, self::PATIENT_1, self::VET_ALICE, '2026-05-11 09:00:00');
    }

    private function start(string $id, string $clinicId, string $admissionId, string $patientId, string $vet, string $startedAt): Consultation
    {
        $consultation = Consultation::startFromAdmission(
            ConsultationId::fromString($id),
            ClinicId::fromString($clinicId),
            AdmissionId::fromString($admissionId),
            PatientId::fromString($patientId),
            UserId::fromString($vet),
            new \DateTimeImmutable($startedAt),
        );

        $this->writeRepository->save($consultation);

        return $consultation;
    }

    private function dayStart(): \DateTimeImmutable
    {
        return new \DateTimeImmutable(self::DAY_START);
    }

    /**
     * @return array{items: list<ConsultationRegisterRow>, total: int}
     */
    private function register(ConsultationRegisterCriteria $criteria): array
    {
        return $this->readRepository->register(ClinicId::fromString(self::CLINIC_ID), $criteria);
    }

    /**
     * @param list<ConsultationRegisterRow> $rows
     *
     * @return list<string>
     */
    private function ids(array $rows): array
    {
        return array_map(static fn (ConsultationRegisterRow $row): string => $row->consultationId, $rows);
    }
}
