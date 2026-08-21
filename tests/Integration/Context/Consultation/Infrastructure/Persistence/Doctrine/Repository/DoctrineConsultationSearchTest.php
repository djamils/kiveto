<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Consultation\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Consultation\Application\Port\ConsultationReadRepositoryInterface;
use App\Context\Consultation\Application\Query\SearchConsultations\ConsultationListRow;
use App\Context\Consultation\Application\Query\SearchConsultations\SearchConsultationsCriteria;
use App\Context\Consultation\Domain\Consultation;
use App\Context\Consultation\Domain\Repository\ConsultationRepositoryInterface;
use App\Context\Consultation\Domain\ValueObject\AdmissionId;
use App\Context\Consultation\Domain\ValueObject\ClinicId;
use App\Context\Consultation\Domain\ValueObject\ConsultationId;
use App\Context\Consultation\Domain\ValueObject\DiagnosisCertainty;
use App\Context\Consultation\Domain\ValueObject\DiagnosisSource;
use App\Context\Consultation\Domain\ValueObject\PatientId;
use App\Context\Consultation\Domain\ValueObject\PlanActionKind;
use App\Context\Consultation\Domain\ValueObject\UserId;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;

/**
 * Covers the clinic-wide list query of the consultation read repository.
 */
final class DoctrineConsultationSearchTest extends KernelTestCase
{
    use Factories;

    private const string CLINIC_ID       = 'c1c1c1c1-c1c1-4c1c-8c1c-c1c1c1c1c1c1';
    private const string OTHER_CLINIC_ID = 'c2c2c2c2-c2c2-4c2c-8c2c-c2c2c2c2c2c2';

    private const string VET_ALICE = 'a1a1a1a1-a1a1-4a1a-8a1a-a1a1a1a1a1a1';
    private const string VET_BOB   = 'b1b1b1b1-b1b1-4b1b-8b1b-b1b1b1b1b1b1';

    private const string PATIENT_1 = 'd1d1d1d1-0000-4000-8000-000000000001';
    private const string PATIENT_2 = 'd1d1d1d1-0000-4000-8000-000000000002';

    private const string CONSULT_1 = '11111111-0000-4000-8000-000000000001';
    private const string CONSULT_2 = '11111111-0000-4000-8000-000000000002';
    private const string CONSULT_3 = '11111111-0000-4000-8000-000000000003';

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
    }

    public function testReturnsTheClinicsConsultationsMostRecentFirst(): void
    {
        $this->seedThree();

        $result = $this->search(new SearchConsultationsCriteria());

        self::assertSame(3, $result['total']);
        self::assertSame(
            [self::CONSULT_3, self::CONSULT_2, self::CONSULT_1],
            $this->idsOf($result['items']),
        );
    }

    public function testAnotherClinicIsNeverVisible(): void
    {
        $this->start(self::CONSULT_1, self::CLINIC_ID, self::PATIENT_1, self::VET_ALICE, '2026-05-01 09:00:00');
        $this->start(self::CONSULT_2, self::OTHER_CLINIC_ID, self::PATIENT_1, self::VET_ALICE, '2026-05-02 09:00:00');

        $result = $this->search(new SearchConsultationsCriteria());

        self::assertSame(1, $result['total']);
        self::assertSame([self::CONSULT_1], $this->idsOf($result['items']));
    }

    public function testAnEmptyPatientRestrictionShortCircuitsWithoutQuerying(): void
    {
        $this->seedThree();

        $result = $this->search(new SearchConsultationsCriteria(restrictToPatientIds: []));

        self::assertSame(['items' => [], 'total' => 0], $result);
    }

    public function testAPatientRestrictionNarrowsTheResult(): void
    {
        $this->seedThree();

        $result = $this->search(new SearchConsultationsCriteria(restrictToPatientIds: [self::PATIENT_2]));

        self::assertSame(1, $result['total']);
        self::assertSame([self::CONSULT_2], $this->idsOf($result['items']));
    }

    public function testFiltersThatMatchNothingReturnAnEmptyPage(): void
    {
        $this->seedThree();

        $result = $this->search(new SearchConsultationsCriteria(
            restrictToPatientIds: [Uuid::v7()->toRfc4122()],
        ));

        self::assertSame(['items' => [], 'total' => 0], $result);
    }

    public function testStatusFilter(): void
    {
        $this->seedThree();

        $result = $this->search(new SearchConsultationsCriteria(statuses: ['CLOSED']));

        self::assertSame([self::CONSULT_1], $this->idsOf($result['items']));
    }

    public function testPractitionerFilter(): void
    {
        $this->seedThree();

        $result = $this->search(new SearchConsultationsCriteria(practitionerUserIds: [self::VET_BOB]));

        self::assertSame([self::CONSULT_2], $this->idsOf($result['items']));
    }

    public function testStartedAfterFilter(): void
    {
        $this->seedThree();

        $result = $this->search(new SearchConsultationsCriteria(
            startedAfterUtc: new \DateTimeImmutable('2026-05-02 00:00:00'),
        ));

        self::assertSame([self::CONSULT_3, self::CONSULT_2], $this->idsOf($result['items']));
    }

    public function testPagination(): void
    {
        $this->seedThree();

        $firstPage = $this->search(new SearchConsultationsCriteria(limit: 2));
        self::assertSame(3, $firstPage['total']);
        self::assertSame([self::CONSULT_3, self::CONSULT_2], $this->idsOf($firstPage['items']));

        $secondPage = $this->search(new SearchConsultationsCriteria(page: 2, limit: 2));
        self::assertSame(3, $secondPage['total']);
        self::assertSame([self::CONSULT_1], $this->idsOf($secondPage['items']));
    }

    public function testAPageBeyondTheLastOneStillReportsTheRealTotal(): void
    {
        $this->seedThree();

        $result = $this->search(new SearchConsultationsCriteria(page: 4, limit: 2));

        self::assertSame(3, $result['total']);
        self::assertSame([], $result['items']);
    }

    public function testFreeTextMatchesTheChiefComplaint(): void
    {
        $this->seedThree();

        $result = $this->search(new SearchConsultationsCriteria(searchTerm: 'Boiterie'));

        self::assertSame([self::CONSULT_1], $this->idsOf($result['items']));
    }

    public function testFreeTextMatchesTheClosingSummary(): void
    {
        $this->seedThree();

        $result = $this->search(new SearchConsultationsCriteria(searchTerm: 'radiographie'));

        self::assertSame([self::CONSULT_1], $this->idsOf($result['items']));
    }

    public function testFreeTextMatchesAMotif(): void
    {
        $this->seedThree();

        $result = $this->search(new SearchConsultationsCriteria(searchTerm: 'Vaccination'));

        self::assertSame([self::CONSULT_2], $this->idsOf($result['items']));
    }

    public function testFreeTextMatchesADiagnosis(): void
    {
        $this->seedThree();

        $result = $this->search(new SearchConsultationsCriteria(searchTerm: 'Otite'));

        self::assertSame([self::CONSULT_3], $this->idsOf($result['items']));
    }

    public function testFreeTextAlsoWidensToThePatientsMatchedByTheCaller(): void
    {
        $this->seedThree();

        // "Luna" matches no consultation field; it reaches the result only
        // through the patient ids the caller resolved from the animal name.
        $result = $this->search(new SearchConsultationsCriteria(
            searchTerm: 'Luna',
            textMatchPatientIds: [self::PATIENT_2],
        ));

        self::assertSame([self::CONSULT_2], $this->idsOf($result['items']));
    }

    public function testSortByDateAscending(): void
    {
        $this->seedThree();

        $result = $this->search(new SearchConsultationsCriteria(direction: 'asc'));

        self::assertSame(
            [self::CONSULT_1, self::CONSULT_2, self::CONSULT_3],
            $this->idsOf($result['items']),
        );
    }

    public function testSortByStatus(): void
    {
        $this->seedThree();

        $result = $this->search(new SearchConsultationsCriteria(
            sort: SearchConsultationsCriteria::SORT_STATUS,
            direction: 'asc',
        ));

        // CLOSED sorts before OPEN, then the date tie-break applies.
        self::assertSame(
            [self::CONSULT_1, self::CONSULT_3, self::CONSULT_2],
            $this->idsOf($result['items']),
        );
    }

    public function testSortByAmount(): void
    {
        $this->seedThree();

        $result = $this->search(new SearchConsultationsCriteria(
            sort: SearchConsultationsCriteria::SORT_AMOUNT,
            direction: 'desc',
        ));

        // 2: 2 × 4000, 1: 3500, 3: no billing line.
        self::assertSame(
            [self::CONSULT_2, self::CONSULT_1, self::CONSULT_3],
            $this->idsOf($result['items']),
        );
    }

    public function testSortByPractitionerFollowsTheOrderSuppliedByTheCaller(): void
    {
        $this->seedThree();

        $result = $this->search(new SearchConsultationsCriteria(
            practitionerOrder: [self::VET_BOB, self::VET_ALICE],
            sort: SearchConsultationsCriteria::SORT_VET,
            direction: 'asc',
        ));

        // Bob first, then Alice's two consultations, most recent first.
        self::assertSame(
            [self::CONSULT_2, self::CONSULT_3, self::CONSULT_1],
            $this->idsOf($result['items']),
        );
    }

    public function testSortByPractitionerWithoutAnOrderStillReturnsEveryRow(): void
    {
        $this->seedThree();

        $result = $this->search(new SearchConsultationsCriteria(
            sort: SearchConsultationsCriteria::SORT_VET,
        ));

        self::assertCount(3, $result['items']);
    }

    public function testRowsCarryTheirMotifsInPositionOrder(): void
    {
        $this->seedThree();

        $rows = $this->rowsById($this->search(new SearchConsultationsCriteria())['items']);

        self::assertSame(['Vaccination', 'Contrôle'], $rows[self::CONSULT_2]->motifs);
        self::assertSame([], $rows[self::CONSULT_3]->motifs);
    }

    public function testRowsCarryTheirPreTaxTotalPerTaxCategoryAndCurrency(): void
    {
        $this->seedThree();

        $rows = $this->rowsById($this->search(new SearchConsultationsCriteria())['items']);

        self::assertSame(['veterinary.act.consultation' => 8000], $rows[self::CONSULT_2]->htByTaxCategory);
        self::assertSame('EUR', $rows[self::CONSULT_2]->currency);

        // A plan action without a tax category lands under the empty key.
        self::assertSame(['' => 3500], $rows[self::CONSULT_1]->htByTaxCategory);

        // No billing line at all: nothing to break down, and no currency to report.
        self::assertSame([], $rows[self::CONSULT_3]->htByTaxCategory);
        self::assertSame('', $rows[self::CONSULT_3]->currency);
    }

    public function testRowsCarryTheConsultationIdentity(): void
    {
        $this->seedThree();

        $rows = $this->rowsById($this->search(new SearchConsultationsCriteria())['items']);
        $row  = $rows[self::CONSULT_1];

        self::assertSame(self::PATIENT_1, $row->patientId);
        self::assertSame(self::VET_ALICE, $row->practitionerUserId);
        self::assertSame('CLOSED', $row->status);
        self::assertSame('2026-05-01 09:00:00', $row->startedAtUtc);
        self::assertSame('2026-05-01 10:00:00', $row->closedAtUtc);
        self::assertSame('Boiterie postérieure', $row->chiefComplaint);
    }

    /**
     * Three consultations of the clinic:
     *   1 — Alice, patient 1, closed, chief complaint + untaxed plan action
     *   2 — Bob, patient 2, open, two motifs + two taxed plan actions
     *   3 — Alice, patient 1, open, a diagnosis and nothing else
     */
    private function seedThree(): void
    {
        $user = UserId::fromString(self::VET_ALICE);

        $first = $this->start(self::CONSULT_1, self::CLINIC_ID, self::PATIENT_1, self::VET_ALICE, '2026-05-01 09:00:00');
        $first->recordChiefComplaint('Boiterie postérieure', new \DateTimeImmutable('2026-05-01 09:05:00'));
        $first->addPlanAction(
            PlanActionKind::PERFORMED_ACT,
            'Consultation',
            null,
            null,
            null,
            null,
            1.0,
            3500,
            'EUR',
            null,
            $user,
            new \DateTimeImmutable('2026-05-01 09:10:00'),
        );
        $first->close($user, 'Contrôle après radiographie', new \DateTimeImmutable('2026-05-01 10:00:00'));
        $this->writeRepository->save($first);

        $second = $this->start(self::CONSULT_2, self::CLINIC_ID, self::PATIENT_2, self::VET_BOB, '2026-05-02 09:00:00');
        $second->setMotifs(['Vaccination', 'Contrôle'], new \DateTimeImmutable('2026-05-02 09:05:00'));
        foreach (['Rappel CHPL', 'Examen général'] as $index => $label) {
            $second->addPlanAction(
                PlanActionKind::PERFORMED_ACT,
                $label,
                null,
                null,
                null,
                null,
                1.0,
                4000,
                'EUR',
                'veterinary.act.consultation',
                UserId::fromString(self::VET_BOB),
                new \DateTimeImmutable(\sprintf('2026-05-02 09:%02d:00', 10 + $index)),
            );
        }
        $this->writeRepository->save($second);

        $third = $this->start(self::CONSULT_3, self::CLINIC_ID, self::PATIENT_1, self::VET_ALICE, '2026-05-03 09:00:00');
        $third->addDiagnosis(
            null,
            'Otite externe',
            DiagnosisCertainty::CERTAIN,
            null,
            true,
            DiagnosisSource::MANUAL,
            $user,
            new \DateTimeImmutable('2026-05-03 09:10:00'),
        );
        $this->writeRepository->save($third);
    }

    private function start(
        string $consultationId,
        string $clinicId,
        string $patientId,
        string $practitionerUserId,
        string $startedAt,
    ): Consultation {
        $consultation = Consultation::startFromAdmission(
            ConsultationId::fromString($consultationId),
            ClinicId::fromString($clinicId),
            AdmissionId::fromString(Uuid::v7()->toRfc4122()),
            PatientId::fromString($patientId),
            UserId::fromString($practitionerUserId),
            new \DateTimeImmutable($startedAt),
        );

        $this->writeRepository->save($consultation);

        return $consultation;
    }

    /**
     * @return array{items: list<ConsultationListRow>, total: int}
     */
    private function search(SearchConsultationsCriteria $criteria): array
    {
        return $this->readRepository->search(ClinicId::fromString(self::CLINIC_ID), $criteria);
    }

    /**
     * @param list<ConsultationListRow> $rows
     *
     * @return list<string>
     */
    private function idsOf(array $rows): array
    {
        return array_map(static fn (ConsultationListRow $row): string => $row->consultationId, $rows);
    }

    /**
     * @param list<ConsultationListRow> $rows
     *
     * @return array<string, ConsultationListRow>
     */
    private function rowsById(array $rows): array
    {
        $byId = [];

        foreach ($rows as $row) {
            $byId[$row->consultationId] = $row;
        }

        return $byId;
    }
}
