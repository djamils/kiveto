<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Consultation\Application\Query\SearchConsultations;

use App\Context\Consultation\Application\Port\ConsultationReadRepositoryInterface;
use App\Context\Consultation\Application\Port\PatientIdsProviderInterface;
use App\Context\Consultation\Application\Port\TaxRateProviderInterface;
use App\Context\Consultation\Application\Query\SearchConsultations\ConsultationListItemView;
use App\Context\Consultation\Application\Query\SearchConsultations\ConsultationListRow;
use App\Context\Consultation\Application\Query\SearchConsultations\SearchConsultations;
use App\Context\Consultation\Application\Query\SearchConsultations\SearchConsultationsCriteria;
use App\Context\Consultation\Application\Query\SearchConsultations\SearchConsultationsHandler;
use App\Context\Consultation\Domain\ValueObject\ClinicId;
use PHPUnit\Framework\TestCase;

final class SearchConsultationsHandlerTest extends TestCase
{
    private const string CLINIC_ID       = '22222222-2222-4222-8222-222222222222';
    private const string CONSULTATION_ID = '11111111-1111-4111-8111-111111111111';
    private const string PATIENT_ID      = '33333333-3333-4333-8333-333333333333';
    private const string PRACTITIONER_ID = '44444444-4444-4444-8444-444444444444';

    public function testAnimalsAreNotResolvedWhenNoAnimalFilterIsActive(): void
    {
        $patientIds = $this->createMock(PatientIdsProviderInterface::class);
        $patientIds->expects(self::never())->method('findPatientIdsForAnimals');

        // No species filter: the restriction stays null instead of becoming an
        // empty list, which would match nothing.
        $repository = $this->repositoryExpecting(new SearchConsultationsCriteria(
            textMatchPatientIds: [],
            restrictToPatientIds: null,
        ));

        $result = $this->handle(
            new SearchConsultations(clinicId: self::CLINIC_ID),
            $repository,
            $patientIds,
        );

        self::assertSame(['items' => [], 'total' => 0], $result);
    }

    public function testEachNonEmptyAnimalListIsResolvedToPatientIds(): void
    {
        $patientIds = $this->createMock(PatientIdsProviderInterface::class);
        $patientIds
            ->expects(self::exactly(2))
            ->method('findPatientIdsForAnimals')
            ->willReturnMap([
                [['animal-1', 'animal-2'], self::CLINIC_ID, ['patient-1', 'patient-2']],
                [['animal-3'], self::CLINIC_ID, ['patient-3']],
            ])
        ;

        $repository = $this->repositoryExpecting(new SearchConsultationsCriteria(
            textMatchPatientIds: ['patient-1', 'patient-2'],
            restrictToPatientIds: ['patient-3'],
        ));

        $result = $this->handle(
            new SearchConsultations(
                clinicId: self::CLINIC_ID,
                textMatchAnimalIds: ['animal-1', 'animal-2'],
                restrictToAnimalIds: ['animal-3'],
            ),
            $repository,
            $patientIds,
        );

        self::assertSame(['items' => [], 'total' => 0], $result);
    }

    public function testASpeciesFilterMatchingNoAnimalStaysAnEmptyRestriction(): void
    {
        $patientIds = $this->createMock(PatientIdsProviderInterface::class);
        $patientIds->expects(self::never())->method('findPatientIdsForAnimals');

        $expected = new SearchConsultationsCriteria(restrictToPatientIds: []);

        self::assertTrue($expected->isImpossible());

        $repository = $this->repositoryExpecting($expected);

        $result = $this->handle(
            new SearchConsultations(clinicId: self::CLINIC_ID, restrictToAnimalIds: []),
            $repository,
            $patientIds,
        );

        self::assertSame(['items' => [], 'total' => 0], $result);
    }

    public function testEveryFilterOfTheQueryReachesTheRepositoryCriteria(): void
    {
        $startedAfterUtc = new \DateTimeImmutable('2026-04-10 00:00:00');

        $repository = $this->repositoryExpecting(new SearchConsultationsCriteria(
            searchTerm: 'Rex',
            startedAfterUtc: $startedAfterUtc,
            statuses: ['OPEN'],
            practitionerUserIds: [self::PRACTITIONER_ID],
            practitionerOrder: [self::PRACTITIONER_ID],
            sort: SearchConsultationsCriteria::SORT_AMOUNT,
            direction: 'asc',
            page: 2,
            limit: 10,
        ));

        $result = $this->handle(
            new SearchConsultations(
                clinicId: self::CLINIC_ID,
                searchTerm: 'Rex',
                startedAfterUtc: $startedAfterUtc,
                statuses: ['OPEN'],
                practitionerUserIds: [self::PRACTITIONER_ID],
                practitionerOrder: [self::PRACTITIONER_ID],
                sort: 'amount',
                direction: 'asc',
                page: 2,
                limit: 10,
            ),
            $repository,
        );

        self::assertSame(['items' => [], 'total' => 0], $result);
    }

    public function testEachTaxCategoryIsAskedForOnlyOnceAcrossThePage(): void
    {
        $calls = [];

        $taxRates = $this->createStub(TaxRateProviderInterface::class);
        $taxRates
            ->method('effectiveRatePercent')
            ->willReturnCallback(static function (string $categoryCode, string $clinicId) use (&$calls): float {
                $calls[] = [$categoryCode, $clinicId];

                return 'veterinary.act.consultation' === $categoryCode ? 20.0 : 10.0;
            })
        ;

        $repository = $this->repositoryReturning([
            $this->row(htByTaxCategory: ['veterinary.act.consultation' => 1000]),
            $this->row(htByTaxCategory: [
                'veterinary.act.consultation' => 2000,
                'veterinary.medicine.oral'    => 500,
            ]),
        ]);

        $result = $this->handle(
            new SearchConsultations(clinicId: self::CLINIC_ID),
            $repository,
            taxRates: $taxRates,
        );

        // The rate cache spans the page, so the shared category is resolved once.
        self::assertSame([
            ['veterinary.act.consultation', self::CLINIC_ID],
            ['veterinary.medicine.oral', self::CLINIC_ID],
        ], $calls);

        self::assertSame(200, $this->itemAt($result, 0)->totalTvaMinorUnits);
        self::assertSame(450, $this->itemAt($result, 1)->totalTvaMinorUnits);
    }

    public function testAnAmountWithoutTaxCategoryCountsInHtOnly(): void
    {
        $taxRates = $this->createMock(TaxRateProviderInterface::class);
        $taxRates->expects(self::never())->method('effectiveRatePercent');

        $result = $this->handle(
            new SearchConsultations(clinicId: self::CLINIC_ID),
            $this->repositoryReturning([$this->row(htByTaxCategory: ['' => 4200])]),
            taxRates: $taxRates,
        );

        $item = $this->itemAt($result, 0);

        self::assertSame(4200, $item->totalHtMinorUnits);
        self::assertSame(0, $item->totalTvaMinorUnits);
        self::assertSame(4200, $item->totalTtcMinorUnits);
    }

    public function testACategoryWithoutRateIsUntaxed(): void
    {
        $taxRates = $this->createStub(TaxRateProviderInterface::class);
        $taxRates->method('effectiveRatePercent')->willReturn(null);

        $result = $this->handle(
            new SearchConsultations(clinicId: self::CLINIC_ID),
            $this->repositoryReturning([$this->row(htByTaxCategory: ['veterinary.act.consultation' => 3500])]),
            taxRates: $taxRates,
        );

        $item = $this->itemAt($result, 0);

        self::assertSame(3500, $item->totalHtMinorUnits);
        self::assertSame(0, $item->totalTvaMinorUnits);
        self::assertSame(3500, $item->totalTtcMinorUnits);
    }

    public function testVatIsRoundedToTheNearestMinorUnit(): void
    {
        $taxRates = $this->createStub(TaxRateProviderInterface::class);
        $taxRates->method('effectiveRatePercent')->willReturn(20.0);

        // 1233 * 20% = 246.6, rounded to 247.
        $result = $this->handle(
            new SearchConsultations(clinicId: self::CLINIC_ID),
            $this->repositoryReturning([$this->row(htByTaxCategory: ['veterinary.act.consultation' => 1233])]),
            taxRates: $taxRates,
        );

        $item = $this->itemAt($result, 0);

        self::assertSame(1233, $item->totalHtMinorUnits);
        self::assertSame(247, $item->totalTvaMinorUnits);
        self::assertSame(1480, $item->totalTtcMinorUnits);
    }

    public function testCurrencyDefaultsWhenTheRowHasNoBillingLineYet(): void
    {
        $result = $this->handle(
            new SearchConsultations(clinicId: self::CLINIC_ID),
            $this->repositoryReturning([$this->row(currency: '')]),
        );

        self::assertSame('EUR', $this->itemAt($result, 0)->currency);
    }

    public function testTheRowCurrencyIsKept(): void
    {
        $result = $this->handle(
            new SearchConsultations(clinicId: self::CLINIC_ID),
            $this->repositoryReturning([$this->row(currency: 'CHF')]),
        );

        self::assertSame('CHF', $this->itemAt($result, 0)->currency);
    }

    public function testTheUnpaginatedTotalIsPassedThrough(): void
    {
        $result = $this->handle(
            new SearchConsultations(clinicId: self::CLINIC_ID),
            $this->repositoryReturning([], 137),
        );

        self::assertSame(['items' => [], 'total' => 137], $result);
    }

    public function testEveryRowFieldIsCopiedOntoTheView(): void
    {
        $result = $this->handle(
            new SearchConsultations(clinicId: self::CLINIC_ID),
            $this->repositoryReturning([
                $this->row(
                    currency: 'CHF',
                    status: 'CLOSED',
                    startedAtUtc: '2026-04-10 09:00:00',
                    closedAtUtc: '2026-04-10 09:30:00',
                    chiefComplaint: 'Boiterie',
                    motifs: ['Vaccination', 'Contrôle'],
                ),
            ], 1),
        );

        $item = $this->itemAt($result, 0);

        self::assertSame(self::CONSULTATION_ID, $item->consultationId);
        self::assertSame(self::PATIENT_ID, $item->patientId);
        self::assertSame(self::PRACTITIONER_ID, $item->practitionerUserId);
        self::assertSame('CLOSED', $item->status);
        self::assertSame('2026-04-10 09:00:00', $item->startedAtUtc);
        self::assertSame('2026-04-10 09:30:00', $item->closedAtUtc);
        self::assertSame('Boiterie', $item->chiefComplaint);
        self::assertSame(['Vaccination', 'Contrôle'], $item->motifs);
        self::assertSame('CHF', $item->currency);
        self::assertSame(0, $item->totalHtMinorUnits);
        self::assertSame(0, $item->totalTvaMinorUnits);
        self::assertSame(0, $item->totalTtcMinorUnits);
        self::assertSame(1, $result['total']);
    }

    /**
     * @return array{items: list<ConsultationListItemView>, total: int}
     */
    private function handle(
        SearchConsultations $query,
        ConsultationReadRepositoryInterface $repository,
        ?PatientIdsProviderInterface $patientIds = null,
        ?TaxRateProviderInterface $taxRates = null,
    ): array {
        $handler = new SearchConsultationsHandler(
            $repository,
            $patientIds ?? $this->createStub(PatientIdsProviderInterface::class),
            $taxRates ?? $this->createStub(TaxRateProviderInterface::class),
        );

        return $handler($query);
    }

    /**
     * Repository expecting one search for the clinic with exactly that criteria.
     */
    private function repositoryExpecting(
        SearchConsultationsCriteria $criteria,
    ): ConsultationReadRepositoryInterface {
        $repository = $this->createMock(ConsultationReadRepositoryInterface::class);
        $repository
            ->expects(self::once())
            ->method('search')
            ->with(self::equalTo(ClinicId::fromString(self::CLINIC_ID)), self::equalTo($criteria))
            ->willReturn(['items' => [], 'total' => 0])
        ;

        return $repository;
    }

    /**
     * @param list<ConsultationListRow> $rows
     */
    private function repositoryReturning(array $rows, int $total = 0): ConsultationReadRepositoryInterface
    {
        $repository = $this->createStub(ConsultationReadRepositoryInterface::class);
        $repository->method('search')->willReturn(['items' => $rows, 'total' => $total]);

        return $repository;
    }

    /**
     * @param array{items: list<ConsultationListItemView>, total: int} $result
     */
    private function itemAt(array $result, int $index): ConsultationListItemView
    {
        self::assertArrayHasKey($index, $result['items']);

        return $result['items'][$index];
    }

    /**
     * @param array<string, int> $htByTaxCategory
     * @param list<string>       $motifs
     */
    private function row(
        array $htByTaxCategory = [],
        string $currency = 'EUR',
        string $status = 'OPEN',
        string $startedAtUtc = '2026-04-10 09:00:00',
        ?string $closedAtUtc = null,
        ?string $chiefComplaint = null,
        array $motifs = [],
    ): ConsultationListRow {
        return new ConsultationListRow(
            consultationId: self::CONSULTATION_ID,
            patientId: self::PATIENT_ID,
            practitionerUserId: self::PRACTITIONER_ID,
            status: $status,
            startedAtUtc: $startedAtUtc,
            closedAtUtc: $closedAtUtc,
            chiefComplaint: $chiefComplaint,
            motifs: $motifs,
            htByTaxCategory: $htByTaxCategory,
            currency: $currency,
        );
    }
}
