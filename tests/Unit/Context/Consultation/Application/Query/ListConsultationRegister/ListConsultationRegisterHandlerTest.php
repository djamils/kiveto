<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Consultation\Application\Query\ListConsultationRegister;

use App\Context\Consultation\Application\Port\ActCategoryProviderInterface;
use App\Context\Consultation\Application\Port\AdmissionFactsProviderInterface;
use App\Context\Consultation\Application\Port\ConsultationReadRepositoryInterface;
use App\Context\Consultation\Application\Port\PatientIdsProviderInterface;
use App\Context\Consultation\Application\Port\TaxRateProviderInterface;
use App\Context\Consultation\Application\Query\ListConsultationRegister\ConsultationRegisterCriteria;
use App\Context\Consultation\Application\Query\ListConsultationRegister\ConsultationRegisterRow;
use App\Context\Consultation\Application\Query\ListConsultationRegister\ListConsultationRegister;
use App\Context\Consultation\Application\Query\ListConsultationRegister\ListConsultationRegisterHandler;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class ListConsultationRegisterHandlerTest extends TestCase
{
    private const string CLINIC = '22222222-2222-4222-8222-222222222222';

    private const string DAY_START = '2026-05-10 22:00:00';

    /** @var ConsultationReadRepositoryInterface&MockObject */
    private ConsultationReadRepositoryInterface $repository;

    /** @var PatientIdsProviderInterface&MockObject */
    private PatientIdsProviderInterface $patientIds;

    /** @var AdmissionFactsProviderInterface&MockObject */
    private AdmissionFactsProviderInterface $admissions;

    /** @var ActCategoryProviderInterface&MockObject */
    private ActCategoryProviderInterface $acts;

    /** @var TaxRateProviderInterface&MockObject */
    private TaxRateProviderInterface $taxRates;

    private ?ConsultationRegisterCriteria $criteria = null;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(ConsultationReadRepositoryInterface::class);
        $this->patientIds = $this->createMock(PatientIdsProviderInterface::class);
        $this->admissions = $this->createMock(AdmissionFactsProviderInterface::class);
        $this->acts       = $this->createMock(ActCategoryProviderInterface::class);
        $this->taxRates   = $this->createMock(TaxRateProviderInterface::class);

        $this->acts->method('actCodesByCategory')->willReturn([
            'STERIL' => ActCategoryProviderInterface::SURGERY,
            'BILAN'  => ActCategoryProviderInterface::LABORATORY,
            'RENAL'  => ActCategoryProviderInterface::LABORATORY,
        ]);
        $this->repository->method('registerCounters')->willReturn(['inProgress' => 2, 'toClose' => 3, 'today' => 4, 'recorded' => 9]);
    }

    public function testAnUnknownKindIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->handler()(new ListConsultationRegister(self::CLINIC, $this->dayStart(), kind: 'VACCINE'));
    }

    public function testAQuietRegisterAsksNothingOfTheOtherContexts(): void
    {
        $this->repository->method('openConsultationAdmissionIds')->willReturn([]);
        $this->rows([]);
        $this->admissions->expects(self::never())->method('closedAmong');
        $this->admissions->expects(self::never())->method('emergenciesAmong');
        $this->admissions->expects(self::never())->method('emergencyAdmissionIds');
        $this->patientIds->expects(self::never())->method('findPatientIdsForAnimals');

        $page = $this->handler()(new ListConsultationRegister(self::CLINIC, $this->dayStart(), page: 2, limit: 50));

        self::assertSame([], $page->items);
        self::assertSame(0, $page->total);
        self::assertSame(2, $page->page);
        self::assertSame(50, $page->perPage);
        self::assertSame([2, 3, 4, 9], [$page->inProgress, $page->toClose, $page->today, $page->recorded]);

        self::assertSame([], $this->criteria()->departedAdmissionIds);
        self::assertNull($this->criteria()->restrictToAdmissionIds);
        self::assertNull($this->criteria()->restrictToActCodes);
    }

    public function testTheFiltersReachTheRepositoryResolved(): void
    {
        $this->repository->method('openConsultationAdmissionIds')->willReturn(['adm-open', 'adm-gone']);
        $this->admissions->expects(self::once())->method('closedAmong')->with(self::CLINIC, ['adm-open', 'adm-gone'])->willReturn(['adm-gone']);
        $this->patientIds->expects(self::once())->method('findPatientIdsForAnimals')->with(['animal-1'], self::CLINIC)->willReturn(['patient-1']);
        $this->rows([]);

        $after = new \DateTimeImmutable('2026-05-01 22:00:00');
        $this->handler()(new ListConsultationRegister(
            clinicId: self::CLINIC,
            dayStartUtc: $this->dayStart(),
            searchTerm: 'otite',
            textMatchAnimalIds: ['animal-1'],
            startedAfterUtc: $after,
            stage: ConsultationRegisterCriteria::STAGE_TO_CLOSE,
            practitionerUserId: 'vet-1',
            restrictToAdmissionIds: ['adm-du'],
            consultationIds: ['cons-1'],
        ));

        self::assertSame('otite', $this->criteria()->searchTerm);
        self::assertSame(['patient-1'], $this->criteria()->textMatchPatientIds);
        self::assertSame($after, $this->criteria()->startedAfterUtc);
        self::assertSame('TO_CLOSE', $this->criteria()->stage);
        self::assertSame(['adm-gone'], $this->criteria()->departedAdmissionIds);
        self::assertSame('vet-1', $this->criteria()->practitionerUserId);
        self::assertSame(['adm-du'], $this->criteria()->restrictToAdmissionIds);
        self::assertSame(['cons-1'], $this->criteria()->consultationIds);
    }

    public function testSurgeriesAndAnalysesNarrowToTheirActCodes(): void
    {
        $this->repository->method('openConsultationAdmissionIds')->willReturn([]);
        $this->rows([]);

        $this->handler()(new ListConsultationRegister(self::CLINIC, $this->dayStart(), kind: ConsultationRegisterCriteria::KIND_SURGERY));
        self::assertSame(['STERIL'], $this->criteria()->restrictToActCodes);

        $this->handler()(new ListConsultationRegister(self::CLINIC, $this->dayStart(), kind: ConsultationRegisterCriteria::KIND_LAB));
        self::assertSame(['BILAN', 'RENAL'], $this->criteria()->restrictToActCodes);
    }

    public function testEmergenciesNarrowTheVisitsAndMeetAnotherRestriction(): void
    {
        $this->repository->method('openConsultationAdmissionIds')->willReturn([]);
        $this->admissions->method('emergencyAdmissionIds')->with(self::CLINIC)->willReturn(['adm-1', 'adm-2']);
        $this->rows([]);

        $this->handler()(new ListConsultationRegister(self::CLINIC, $this->dayStart(), kind: ConsultationRegisterCriteria::KIND_URGENT));
        self::assertSame(['adm-1', 'adm-2'], $this->criteria()->restrictToAdmissionIds);

        $this->handler()(new ListConsultationRegister(
            self::CLINIC,
            $this->dayStart(),
            kind: ConsultationRegisterCriteria::KIND_URGENT,
            restrictToAdmissionIds: ['adm-2', 'adm-3'],
        ));
        self::assertSame(['adm-2'], $this->criteria()->restrictToAdmissionIds);
    }

    public function testRowsCarryTheirStageFlagsDurationAndTotals(): void
    {
        $this->repository->method('openConsultationAdmissionIds')->willReturn(['adm-today', 'adm-gone', 'adm-old']);
        $this->admissions->method('closedAmong')->willReturn(['adm-gone']);
        $this->admissions->expects(self::once())->method('emergenciesAmong')
            ->with(self::CLINIC, ['adm-closed', 'adm-today', 'adm-gone', 'adm-old'])
            ->willReturn(['adm-today'])
        ;
        $this->taxRates->expects(self::exactly(2))->method('effectiveRatePercent')->willReturnMap([
            ['veterinary.act', self::CLINIC, 20.0],
            ['exempt', self::CLINIC, null],
        ]);

        $this->rows([
            $this->row('cons-closed', 'adm-closed', 'CLOSED', '2026-05-09 08:00:00', '2026-05-09 08:24:30', [
                ['code' => 'STERIL', 'label' => 'Ovariectomie'],
                ['code' => null, 'label' => 'Ligne libre'],
            ], ['veterinary.act' => 10000, '' => 500, 'exempt' => 300], 'EUR'),
            $this->row('cons-today', 'adm-today', 'OPEN', '2026-05-11 07:00:00', null, [
                ['code' => 'BILAN', 'label' => 'Bilan sanguin'],
                ['code' => 'RENAL', 'label' => 'Bilan rénal'],
                ['code' => 'OTHER', 'label' => 'Consultation'],
            ], ['veterinary.act' => 2000], ''),
            $this->row('cons-gone', 'adm-gone', 'OPEN', '2026-05-11 06:00:00', null, [], [], ''),
            $this->row('cons-old', 'adm-old', 'OPEN', '2026-05-10 09:00:00', null, [], [], 'EUR'),
        ]);

        $page  = $this->handler()(new ListConsultationRegister(self::CLINIC, $this->dayStart()));
        $items = [];

        foreach ($page->items as $item) {
            $items[$item->consultationId] = $item;
        }

        $closed = $items['cons-closed'];
        self::assertSame('CLOSED', $closed->stage);
        self::assertSame(24, $closed->durationMinutes);
        self::assertTrue($closed->hasSurgery);
        self::assertFalse($closed->isUrgent);
        self::assertSame([], $closed->labAnalyses);
        self::assertSame(10800, $closed->totalHtMinorUnits);
        self::assertSame(2000, $closed->totalTvaMinorUnits);
        self::assertSame(12800, $closed->totalTtcMinorUnits);
        self::assertSame('EUR', $closed->currency);
        self::assertSame('Diagnostic', $closed->primaryDiagnosis);
        self::assertSame('3.900', $closed->weightKg);
        self::assertSame('Résumé', $closed->summary);
        self::assertSame('Motif', $closed->chiefComplaint);
        self::assertSame(['Motif'], $closed->motifs);
        self::assertSame('patient', $closed->patientId);
        self::assertSame('vet', $closed->practitionerUserId);
        self::assertSame('2026-05-09 08:24:30', $closed->closedAtUtc);
        self::assertCount(2, $closed->billedItems);

        $today = $items['cons-today'];
        self::assertSame('IN_PROGRESS', $today->stage);
        self::assertNull($today->durationMinutes);
        self::assertTrue($today->isUrgent);
        self::assertFalse($today->hasSurgery);
        self::assertSame(['Bilan sanguin', 'Bilan rénal'], $today->labAnalyses);
        self::assertSame(2400, $today->totalTtcMinorUnits);
        self::assertSame('EUR', $today->currency);

        self::assertSame('TO_CLOSE', $items['cons-gone']->stage);
        self::assertSame('TO_CLOSE', $items['cons-old']->stage);
    }

    private function criteria(): ConsultationRegisterCriteria
    {
        self::assertNotNull($this->criteria);

        return $this->criteria;
    }

    private function dayStart(): \DateTimeImmutable
    {
        return new \DateTimeImmutable(self::DAY_START, new \DateTimeZone('UTC'));
    }

    /**
     * @param list<ConsultationRegisterRow> $rows
     */
    private function rows(array $rows): void
    {
        $this->repository->method('register')->willReturnCallback(function ($clinicId, ConsultationRegisterCriteria $criteria) use ($rows): array {
            $this->criteria = $criteria;

            return ['items' => $rows, 'total' => \count($rows)];
        });
    }

    /**
     * @param list<array{code: ?string, label: string}> $billed
     * @param array<string, int>                        $ht
     */
    private function row(string $id, string $admissionId, string $status, string $startedAt, ?string $closedAt, array $billed, array $ht, string $currency): ConsultationRegisterRow
    {
        return new ConsultationRegisterRow(
            consultationId: $id,
            admissionId: $admissionId,
            patientId: 'patient',
            practitionerUserId: 'vet',
            status: $status,
            startedAtUtc: $startedAt,
            closedAtUtc: $closedAt,
            chiefComplaint: 'Motif',
            summary: 'Résumé',
            motifs: ['Motif'],
            primaryDiagnosis: 'Diagnostic',
            weightKg: '3.900',
            billedItems: $billed,
            htByTaxCategory: $ht,
            ttcByTaxCategory: [],
            currency: $currency,
        );
    }

    private function handler(): ListConsultationRegisterHandler
    {
        return new ListConsultationRegisterHandler($this->repository, $this->patientIds, $this->admissions, $this->acts, $this->taxRates);
    }
}
