<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Prescription\Application\Query;

use App\Context\Prescription\Application\Port\DrugDetailsDto;
use App\Context\Prescription\Application\Port\PrescriptionReadRepositoryInterface;
use App\Context\Prescription\Application\Query\GetPrescriptionDetails\GetPrescriptionDetails;
use App\Context\Prescription\Application\Query\GetPrescriptionDetails\GetPrescriptionDetailsHandler;
use App\Context\Prescription\Application\Query\GetPrescriptionDetails\PrescriptionDetails;
use App\Context\Prescription\Application\Query\ListPrescriptionsForConsultation\ConsultationPrescriptionRow;
use App\Context\Prescription\Application\Query\ListPrescriptionsForConsultation\ListPrescriptionsForConsultation;
use App\Context\Prescription\Application\Query\ListPrescriptionsForConsultation\ListPrescriptionsForConsultationHandler;
use App\Context\Prescription\Application\Query\SearchPrescriptions\PrescriptionListItemView;
use App\Context\Prescription\Application\Query\SearchPrescriptions\PrescriptionListLine;
use App\Context\Prescription\Application\Query\SearchPrescriptions\PrescriptionListRow;
use App\Context\Prescription\Application\Query\SearchPrescriptions\SearchPrescriptions;
use App\Context\Prescription\Application\Query\SearchPrescriptions\SearchPrescriptionsCriteria;
use App\Context\Prescription\Application\Query\SearchPrescriptions\SearchPrescriptionsHandler;
use App\Context\Prescription\Domain\ValueObject\ClinicId;
use App\Context\Prescription\Domain\ValueObject\ConsultationId;
use App\Context\Prescription\Domain\ValueObject\PrescriptionId;
use PHPUnit\Framework\TestCase;

final class PrescriptionQueryHandlersTest extends TestCase
{
    private const string CLINIC_ID       = '01960000-0000-7000-8000-000000000701';
    private const string PRESCRIPTION_ID = '01960000-0000-7000-8000-000000000702';
    private const string CONSULTATION_ID = '01960000-0000-7000-8000-000000000703';

    public function testGettingAPrescriptionForwardsTheClinicScope(): void
    {
        $details = $this->details();

        $repository = $this->createMock(PrescriptionReadRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('details')
            ->with(
                self::callback(static fn (ClinicId $id): bool => self::CLINIC_ID === $id->toString()),
                self::callback(static fn (PrescriptionId $id): bool => self::PRESCRIPTION_ID === $id->toString()),
            )
            ->willReturn($details)
        ;

        $result = (new GetPrescriptionDetailsHandler($repository))(
            new GetPrescriptionDetails(self::CLINIC_ID, self::PRESCRIPTION_ID),
        );

        self::assertSame($details, $result);
    }

    public function testTheOrdonnancesOfAConsultationAreListedInTheClinicScope(): void
    {
        $rows = [
            new ConsultationPrescriptionRow('01960000-0000-7000-8000-000000000801', 'ORD-2026-000001', 'ISSUED', 2),
            new ConsultationPrescriptionRow('01960000-0000-7000-8000-000000000802', null, 'DRAFT', 1),
            new ConsultationPrescriptionRow('01960000-0000-7000-8000-000000000803', 'ORD-2026-000002', 'CANCELLED', 3),
        ];

        $repository = $this->createMock(PrescriptionReadRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('forConsultation')
            ->with(
                self::callback(static fn (ClinicId $id): bool => self::CLINIC_ID === $id->toString()),
                self::callback(static fn (ConsultationId $id): bool => self::CONSULTATION_ID === $id->toString()),
            )
            ->willReturn($rows)
        ;

        $result = (new ListPrescriptionsForConsultationHandler($repository))(
            new ListPrescriptionsForConsultation(self::CLINIC_ID, self::CONSULTATION_ID),
        );

        self::assertSame($rows, $result);

        // Only a signed, uncancelled ordonnance is handed to a client.
        self::assertTrue($rows[0]->isPrintable());
        self::assertFalse($rows[1]->isPrintable());
        self::assertFalse($rows[2]->isPrintable());
    }

    public function testSearchingWrapsTheRowsWithTheirPager(): void
    {
        $rows = [$this->row(), $this->row()];

        $repository = $this->createMock(PrescriptionReadRepositoryInterface::class);
        $repository->expects(self::once())->method('search')->willReturn(['items' => $rows, 'total' => 30]);

        $view = (new SearchPrescriptionsHandler($repository))(new SearchPrescriptions(
            self::CLINIC_ID,
            new SearchPrescriptionsCriteria(page: 2, perPage: 25),
        ));

        self::assertInstanceOf(PrescriptionListItemView::class, $view);
        self::assertSame($rows, $view->items);
        self::assertSame(30, $view->total);
        self::assertSame(2, $view->page);
        self::assertSame(25, $view->perPage);
        self::assertSame(2, $view->pageCount());
    }

    public function testTheCriteriaComputesItsOffset(): void
    {
        self::assertSame(0, (new SearchPrescriptionsCriteria(page: 1, perPage: 25))->offset());
        self::assertSame(50, (new SearchPrescriptionsCriteria(page: 3, perPage: 25))->offset());
        self::assertSame(0, (new SearchPrescriptionsCriteria(page: 0, perPage: 25))->offset());
    }

    public function testAReversedDateRangeCanMatchNothing(): void
    {
        $impossible = new SearchPrescriptionsCriteria(
            issuedFrom: new \DateTimeImmutable('2026-08-22'),
            issuedTo: new \DateTimeImmutable('2026-08-01'),
        );

        self::assertTrue($impossible->isImpossible());
        self::assertFalse((new SearchPrescriptionsCriteria())->isImpossible());
        self::assertFalse(
            (new SearchPrescriptionsCriteria(issuedTo: new \DateTimeImmutable('2026-08-01')))->isImpossible(),
        );
    }

    public function testAnEmptyPageStillCountsAsOne(): void
    {
        self::assertSame(1, new PrescriptionListItemView([], 0, 1, 25)->pageCount());
    }

    public function testAListRowKnowsWhetherItHasExpired(): void
    {
        $row = $this->row(validUntil: new \DateTimeImmutable('2026-09-19 10:00:00'));

        self::assertFalse($row->isExpiredAt(new \DateTimeImmutable('2026-09-01')));
        self::assertTrue($row->isExpiredAt(new \DateTimeImmutable('2026-10-01')));
        // A draft has no validity and therefore cannot expire.
        self::assertFalse($this->row()->isExpiredAt(new \DateTimeImmutable('2030-01-01')));
    }

    public function testAnUnknownMedicineCarriesNothing(): void
    {
        $details = DrugDetailsDto::unknown();

        self::assertNull($details->pharmaceuticalForm);
        self::assertSame([], $details->activeSubstances);
        self::assertSame('NONE', $details->prescriptionClass);
    }

    private function details(): PrescriptionDetails
    {
        return new PrescriptionDetails(
            id: self::PRESCRIPTION_ID,
            clinicId: self::CLINIC_ID,
            consultationId: '01960000-0000-7000-8000-000000000703',
            number: 'ORD-2026-000001',
            status: 'ISSUED',
            statusLabel: 'Émise',
            isExpired: false,
            isDispensable: true,
            prescriberName: 'Dr Rousseau',
            prescriberRegistrationNumber: 'FR-12345',
            patientName: 'Rex',
            patientSpecies: 'dog',
            patientBreed: 'Labrador',
            patientBirthDate: null,
            patientMicrochipNumber: null,
            ownerClientId: null,
            ownerName: 'Sophie Dupont',
            ownerAddressLines: [],
            lines: [],
            renewals: [],
            renewalsAllowed: 0,
            renewalsUsed: 0,
            renewalsRemaining: 0,
            carriesControlledSubstance: false,
            validityDays: 365,
            issuedAt: new \DateTimeImmutable('2026-08-22 10:00:00'),
            validUntil: new \DateTimeImmutable('2027-08-22 10:00:00'),
            notes: null,
            createdAt: new \DateTimeImmutable('2026-08-22 10:00:00'),
            cancelledAt: null,
            cancellationReason: null,
            prescriberId: '01960000-0000-7000-8000-000000000704',
            patientAnimalId: null,
            patientWeightKg: null,
        );
    }

    private function row(?\DateTimeImmutable $validUntil = null): PrescriptionListRow
    {
        return new PrescriptionListRow(
            id: self::PRESCRIPTION_ID,
            number: 'ORD-2026-000001',
            status: 'ISSUED',
            patientName: 'Rex',
            patientSpecies: 'dog',
            ownerName: 'Sophie Dupont',
            prescriberName: 'Dr Rousseau',
            lineCount: 2,
            carriesControlledSubstance: false,
            renewalsRemaining: 1,
            createdAt: new \DateTimeImmutable('2026-08-22 10:00:00'),
            issuedAt: new \DateTimeImmutable('2026-08-22 10:00:00'),
            validUntil: $validUntil,
            consultationId: '01960000-0000-7000-8000-000000000703',
            prescriberId: '01960000-0000-7000-8000-000000000704',
            lines: [new PrescriptionListLine('Meloxidyl', 'RX')],
        );
    }
}
