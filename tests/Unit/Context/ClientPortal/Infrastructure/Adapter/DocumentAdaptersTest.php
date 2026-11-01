<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Infrastructure\Adapter;

use App\Context\Admission\Application\Query\GetAdmissionVisit\AdmissionVisitView;
use App\Context\Admission\Application\Query\GetAdmissionVisit\GetAdmissionVisit;
use App\Context\Billing\Application\Command\EnsureSentInvoiceFile\EnsureSentInvoiceFile;
use App\Context\Billing\Application\Command\EnsureSentInvoiceFile\SentDocumentFile;
use App\Context\Billing\Application\Query\GetInvoiceDetails\GetInvoiceDetails;
use App\Context\Billing\Application\Query\GetInvoiceDetails\InvoiceDetails;
use App\Context\Billing\Application\Query\ListInvoicePortalStatuses\InvoicePortalStatus;
use App\Context\Billing\Application\Query\ListInvoicePortalStatuses\ListInvoicePortalStatuses;
use App\Context\Billing\Application\Query\ListPortalPublishableInvoices\ListPortalPublishableInvoices;
use App\Context\Billing\Application\Query\ListPortalPublishableInvoices\PortalPublishableInvoice;
use App\Context\ClientPortal\Application\Port\InvoicePortalState;
use App\Context\ClientPortal\Application\Port\ReportShareability;
use App\Context\ClientPortal\Infrastructure\Adapter\Admission\QueryBusVisitPatients;
use App\Context\ClientPortal\Infrastructure\Adapter\Billing\BusInvoiceDocuments;
use App\Context\ClientPortal\Infrastructure\Adapter\Consultation\BusConsultationDocuments;
use App\Context\ClientPortal\Infrastructure\Adapter\Prescription\BusPrescriptionDocuments;
use App\Context\Consultation\Application\Command\RenderConsultationReportFile\ConsultationReportFile;
use App\Context\Consultation\Application\Command\RenderConsultationReportFile\RenderConsultationReportFile;
use App\Context\Consultation\Application\Query\GetConsultationShareability\ConsultationShareability;
use App\Context\Consultation\Application\Query\GetConsultationShareability\GetConsultationShareability;
use App\Context\Consultation\Application\Query\ListConsultationIdsForAdmission\ListConsultationIdsForAdmission;
use App\Context\Patient\Application\Query\GetPatientAnimalLink\GetPatientAnimalLink;
use App\Context\Patient\Application\Query\GetPatientAnimalLink\PatientAnimalLinkDto;
use App\Context\Prescription\Application\Command\RenderPrescriptionFile\PrescriptionFile;
use App\Context\Prescription\Application\Command\RenderPrescriptionFile\RenderPrescriptionFile;
use App\Context\Prescription\Application\Query\GetPrescriptionDetails\GetPrescriptionDetails;
use App\Context\Prescription\Application\Query\GetPrescriptionDetails\PrescriptionDetails;
use App\Context\Prescription\Application\Query\GetPrescriptionDetails\PrescriptionLineView;
use App\Context\Prescription\Application\Query\ListPortalPublishablePrescriptions\ListPortalPublishablePrescriptions;
use App\Context\Prescription\Application\Query\ListPortalPublishablePrescriptions\PortalPublishablePrescription;
use App\Context\Prescription\Application\Query\ListPrescriptionPortalSummaries\ListPrescriptionPortalSummaries;
use App\Context\Prescription\Application\Query\ListPrescriptionPortalSummaries\PrescriptionPortalSummary;
use App\Tests\Support\AnsweringQueryBus;
use App\Tests\Unit\Context\ClientPortal\Support\AnsweringCommandBus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DocumentAdaptersTest extends TestCase
{
    private const string ISSUED_AT = '2026-10-16 08:30:00';

    public function testAnIssuedInvoiceWithAPayerClientIsPublished(): void
    {
        $bus      = new AnsweringQueryBus([GetInvoiceDetails::class => self::invoice('PARTIALLY_PAID')]);
        $invoices = new BusInvoiceDocuments($bus, self::commandBus());

        $publication = $invoices->publication('clinic-1', 'inv-1');

        self::assertNotNull($publication);
        self::assertSame(['inv-1', 'client-1', 'FAC-2026-000012', self::ISSUED_AT, 'adm-1'], [
            $publication->invoiceId, $publication->payerClientId, $publication->number, $publication->issuedAt->format('Y-m-d H:i:s'), $publication->admissionId,
        ]);
        $query = $bus->asked[0];
        self::assertInstanceOf(GetInvoiceDetails::class, $query);
        self::assertSame(['clinic-1', 'inv-1'], [$query->clinicId, $query->invoiceId]);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function provideAnInvoiceThatIsNotPublishableAnswersNullCases(): iterable
    {
        yield 'unknown answer' => [null];
        yield 'draft' => [self::invoice('DRAFT')];
        yield 'cancelled' => [self::invoice('CANCELLED')];
        yield 'counter sale' => [self::invoice('ISSUED', payerClientId: null)];
        yield 'without number' => [self::invoice('ISSUED', number: null)];
        yield 'not issued yet' => [self::invoice('ISSUED', issuedAt: null)];
        yield 'unknown invoice' => [static fn (): never => throw new \DomainException('Unknown invoice')];
        yield 'malformed id' => [static fn (): never => throw new \InvalidArgumentException('Malformed id')];
    }

    #[DataProvider('provideAnInvoiceThatIsNotPublishableAnswersNullCases')]
    public function testAnInvoiceThatIsNotPublishableAnswersNull(mixed $answer): void
    {
        $invoices = new BusInvoiceDocuments(new AnsweringQueryBus([GetInvoiceDetails::class => $answer]), self::commandBus());

        self::assertNull($invoices->publication('clinic-1', 'inv-1'));
    }

    public function testTheInvoiceFileIsBillingsSentFile(): void
    {
        $commands = self::commandBus(static fn (): SentDocumentFile => new SentDocumentFile('billing/sent/invoice/inv-1.pdf', 'FAC-2026-000012.pdf'));

        self::assertSame('billing/sent/invoice/inv-1.pdf', new BusInvoiceDocuments(new AnsweringQueryBus(), $commands)->ensureFile('clinic-1', 'inv-1'));

        $command = $commands->commands[0];
        self::assertInstanceOf(EnsureSentInvoiceFile::class, $command);
        self::assertSame(['clinic-1', 'inv-1'], [$command->clinicId, $command->invoiceId]);
    }

    public function testTheInvoiceStatesAreKeyedByIdWithThePortalPaymentStatus(): void
    {
        $bus = new AnsweringQueryBus([ListInvoicePortalStatuses::class => [
            new InvoicePortalStatus('inv-1', 'ISSUED', 12000, 'EUR'),
            new InvoicePortalStatus('inv-2', 'PARTIALLY_PAID', 5000, 'EUR'),
            new InvoicePortalStatus('inv-3', 'PAID', 3000, 'EUR'),
            new InvoicePortalStatus('inv-4', 'CREDITED', 2000, 'EUR'),
            new InvoicePortalStatus('inv-5', 'CANCELLED', 1000, 'EUR'),
        ]]);
        $invoices = new BusInvoiceDocuments($bus, self::commandBus());

        $states = $invoices->states('clinic-1', ['inv-1', 'inv-2', 'inv-3', 'inv-4', 'inv-5', 'inv-6']);

        self::assertSame([
            'inv-1' => [InvoicePortalState::UNPAID, 12000, 'EUR'],
            'inv-2' => [InvoicePortalState::PARTIALLY_PAID, 5000, 'EUR'],
            'inv-3' => [InvoicePortalState::PAID, 3000, 'EUR'],
            'inv-4' => [InvoicePortalState::CREDITED, 2000, 'EUR'],
            'inv-5' => [null, 1000, 'EUR'],
        ], array_map(static fn (InvoicePortalState $state): array => [$state->paymentStatus, $state->totalMinorUnits, $state->currency], $states));
        $query = $bus->asked[0];
        self::assertInstanceOf(ListInvoicePortalStatuses::class, $query);
        self::assertSame(['clinic-1', ['inv-1', 'inv-2', 'inv-3', 'inv-4', 'inv-5', 'inv-6']], [$query->clinicId, $query->invoiceIds]);

        self::assertSame([], $invoices->states('clinic-1', []));
        self::assertCount(1, $bus->asked);
    }

    public function testThePublishableInvoicesArePagedSources(): void
    {
        $from = new \DateTimeImmutable('2026-01-01');
        $bus  = new AnsweringQueryBus([ListPortalPublishableInvoices::class => [new PortalPublishableInvoice('inv-1', 'clinic-1'), new PortalPublishableInvoice('inv-2', 'clinic-2')]]);

        $sources = new BusInvoiceDocuments($bus, self::commandBus())->publishableSince($from, 'inv-0', 50);

        self::assertSame([['inv-1', 'clinic-1'], ['inv-2', 'clinic-2']], array_map(static fn ($s): array => [$s->id, $s->clinicId], $sources));
        $query = $bus->asked[0];
        self::assertInstanceOf(ListPortalPublishableInvoices::class, $query);
        self::assertSame([$from, 'inv-0', 50], [$query->issuedFrom, $query->afterId, $query->limit]);
    }

    public function testAnIssuedPrescriptionWithAnOwnerIsPublished(): void
    {
        $bus           = new AnsweringQueryBus([GetPrescriptionDetails::class => self::prescription('ISSUED')]);
        $prescriptions = new BusPrescriptionDocuments($bus, self::commandBus());

        $publication = $prescriptions->publication('clinic-1', 'rx-1');

        self::assertNotNull($publication);
        self::assertSame(['rx-1', 'client-1', 'ORD-2026-000391', self::ISSUED_AT, 'cons-1', 'animal-1', 'Dr Claire Lambert', 'user-1'], [
            $publication->prescriptionId, $publication->ownerClientId, $publication->number, $publication->issuedAt->format('Y-m-d H:i:s'), $publication->consultationId, $publication->animalId, $publication->prescriberName, $publication->prescriberUserId,
        ]);
        $query = $bus->asked[0];
        self::assertInstanceOf(GetPrescriptionDetails::class, $query);
        self::assertSame(['clinic-1', 'rx-1'], [$query->clinicId, $query->prescriptionId]);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function provideAPrescriptionThatIsNotPublishableAnswersNullCases(): iterable
    {
        yield 'unknown answer' => [null];
        yield 'draft' => [self::prescription('DRAFT')];
        yield 'cancelled' => [self::prescription('CANCELLED')];
        yield 'without owner' => [self::prescription('ISSUED', ownerClientId: null)];
        yield 'without number' => [self::prescription('ISSUED', number: null)];
        yield 'without issue date' => [self::prescription('ISSUED', issuedAt: null)];
        yield 'unknown prescription' => [static fn (): never => throw new \DomainException('Unknown prescription')];
        yield 'malformed id' => [static fn (): never => throw new \InvalidArgumentException('Malformed id')];
    }

    #[DataProvider('provideAPrescriptionThatIsNotPublishableAnswersNullCases')]
    public function testAPrescriptionThatIsNotPublishableAnswersNull(mixed $answer): void
    {
        $prescriptions = new BusPrescriptionDocuments(new AnsweringQueryBus([GetPrescriptionDetails::class => $answer]), self::commandBus());

        self::assertNull($prescriptions->publication('clinic-1', 'rx-1'));
    }

    public function testThePrescriptionFileIsRenderedUnderTheGivenKey(): void
    {
        $commands = self::commandBus(static fn (): PrescriptionFile => new PrescriptionFile('portal/rx-1/v1.pdf', 'ORD-2026-000391.pdf'));

        new BusPrescriptionDocuments(new AnsweringQueryBus(), $commands)->renderFile('clinic-1', 'rx-1', 'portal/rx-1/v1.pdf');

        $command = $commands->commands[0];
        self::assertInstanceOf(RenderPrescriptionFile::class, $command);
        self::assertSame(['clinic-1', 'rx-1', 'portal/rx-1/v1.pdf'], [$command->clinicId, $command->prescriptionId, $command->storageKey]);
    }

    public function testThePrescriptionStatesAreKeyedById(): void
    {
        $validUntil = new \DateTimeImmutable('2027-10-16');
        $bus        = new AnsweringQueryBus([ListPrescriptionPortalSummaries::class => [
            new PrescriptionPortalSummary('rx-1', 'ISSUED', $validUntil, true),
            new PrescriptionPortalSummary('rx-2', 'CANCELLED', null, false),
        ]]);
        $prescriptions = new BusPrescriptionDocuments($bus, self::commandBus());

        $states = $prescriptions->states('clinic-1', ['rx-1', 'rx-2', 'rx-3']);

        self::assertSame(['rx-1', 'rx-2'], array_keys($states));
        self::assertSame([true, $validUntil, true], [$states['rx-1']->issued, $states['rx-1']->validUntil, $states['rx-1']->renewable]);
        self::assertSame([false, null, false], [$states['rx-2']->issued, $states['rx-2']->validUntil, $states['rx-2']->renewable]);
        $query = $bus->asked[0];
        self::assertInstanceOf(ListPrescriptionPortalSummaries::class, $query);
        self::assertSame(['clinic-1', ['rx-1', 'rx-2', 'rx-3']], [$query->clinicId, $query->prescriptionIds]);

        self::assertSame([], $prescriptions->states('clinic-1', []));
        self::assertCount(1, $bus->asked);
    }

    public function testTheContentOfAnIssuedPrescriptionIsItsLines(): void
    {
        $prescriptions = new BusPrescriptionDocuments(new AnsweringQueryBus([GetPrescriptionDetails::class => self::prescription('ISSUED', ownerClientId: null)]), self::commandBus());

        $content = $prescriptions->content('clinic-1', 'rx-1');

        self::assertNotNull($content);
        self::assertSame(['rx-1', self::ISSUED_AT, 'Dr Claire Lambert', 'animal-1', 'Milo'], [
            $content->prescriptionId, $content->issuedAt->format('Y-m-d H:i:s'), $content->prescriberName, $content->animalId, $content->patientName,
        ]);
        self::assertCount(1, $content->lines);
        self::assertSame(['Previcox 57 mg', '1 boîte', '1 comprimé par jour pendant 10 jours'], [$content->lines[0]->label, $content->lines[0]->quantity, $content->lines[0]->posology]);
    }

    public function testTheContentOfAPrescriptionNotIssuedIsNull(): void
    {
        $content = static fn (mixed $answer): mixed => new BusPrescriptionDocuments(new AnsweringQueryBus([GetPrescriptionDetails::class => $answer]), self::commandBus())->content('clinic-1', 'rx-1');

        self::assertNull($content(self::prescription('DRAFT')));
        self::assertNull($content(self::prescription('ISSUED', issuedAt: null)));
        self::assertNull($content(static fn (): never => throw new \DomainException('Unknown prescription')));
    }

    public function testThePublishablePrescriptionsArePagedSources(): void
    {
        $from = new \DateTimeImmutable('2026-01-01');
        $bus  = new AnsweringQueryBus([ListPortalPublishablePrescriptions::class => [new PortalPublishablePrescription('rx-1', 'clinic-1')]]);

        $sources = new BusPrescriptionDocuments($bus, self::commandBus())->publishableSince($from, null, 100);

        self::assertSame([['rx-1', 'clinic-1']], array_map(static fn ($s): array => [$s->id, $s->clinicId], $sources));
        $query = $bus->asked[0];
        self::assertInstanceOf(ListPortalPublishablePrescriptions::class, $query);
        self::assertSame([$from, null, 100], [$query->issuedFrom, $query->afterId, $query->limit]);
    }

    public function testTheShareabilityOfAConsultationIsConsultations(): void
    {
        $startedAt = new \DateTimeImmutable('2026-10-16 08:00:00', new \DateTimeZone('UTC'));
        $closedAt  = new \DateTimeImmutable('2026-10-16 08:40:00', new \DateTimeZone('UTC'));
        $bus       = new AnsweringQueryBus([GetConsultationShareability::class => new ConsultationShareability(false, ConsultationShareability::NO_PRIMARY_OWNER, 'patient-1', 'animal-1', null, $startedAt, $closedAt, 'Dr Claire Lambert')]);
        $documents = new BusConsultationDocuments($bus, self::commandBus());

        $share = $documents->shareability('clinic-1', 'cons-1');

        self::assertNotNull($share);
        self::assertSame([ReportShareability::NO_PRIMARY_OWNER, 'patient-1', 'animal-1', null, $startedAt, $closedAt, 'Dr Claire Lambert'], [
            $share->refusal, $share->patientId, $share->animalId, $share->primaryOwnerClientId, $share->startedAt, $share->closedAt, $share->practitionerName,
        ]);
        $query = $bus->asked[0];
        self::assertInstanceOf(GetConsultationShareability::class, $query);
        self::assertSame(['clinic-1', 'cons-1'], [$query->clinicId, $query->consultationId]);

        self::assertNull(new BusConsultationDocuments(new AnsweringQueryBus(), self::commandBus())->shareability('clinic-1', 'cons-1'));
    }

    public function testTheReportIsRenderedUnderTheGivenKey(): void
    {
        $commands = self::commandBus(static fn (): ConsultationReportFile => new ConsultationReportFile('portal/cons-1/v2.pdf', 'compte-rendu.pdf'));

        new BusConsultationDocuments(new AnsweringQueryBus(), $commands)->renderReport('clinic-1', 'cons-1', 'portal/cons-1/v2.pdf');

        $command = $commands->commands[0];
        self::assertInstanceOf(RenderConsultationReportFile::class, $command);
        self::assertSame(['clinic-1', 'cons-1', 'portal/cons-1/v2.pdf'], [$command->clinicId, $command->consultationId, $command->storageKey]);
    }

    public function testTheConsultationsOfAVisitAreConsultations(): void
    {
        $bus = new AnsweringQueryBus([ListConsultationIdsForAdmission::class => ['cons-1', 'cons-2']]);

        self::assertSame(['cons-1', 'cons-2'], new BusConsultationDocuments($bus, self::commandBus())->consultationsOfAdmission('clinic-1', 'adm-1'));

        $query = $bus->asked[0];
        self::assertInstanceOf(ListConsultationIdsForAdmission::class, $query);
        self::assertSame(['clinic-1', 'adm-1'], [$query->clinicId, $query->admissionId]);
    }

    public function testTheVisitLeadsToItsPatientThenItsAnimal(): void
    {
        $bus = new AnsweringQueryBus([
            GetAdmissionVisit::class    => new AdmissionVisitView('adm-1', 'patient-1', new \DateTimeImmutable()),
            GetPatientAnimalLink::class => new PatientAnimalLinkDto('patient-1', 'animal-1', 'Milo', null, null),
        ]);
        $visits = new QueryBusVisitPatients($bus);

        self::assertSame('patient-1', $visits->patientOfAdmission('clinic-1', 'adm-1'));
        self::assertSame('animal-1', $visits->animalOfPatient('clinic-1', 'patient-1'));

        [$visit, $link] = $bus->asked;
        self::assertInstanceOf(GetAdmissionVisit::class, $visit);
        self::assertSame(['clinic-1', 'adm-1'], [$visit->clinicId, $visit->admissionId]);
        self::assertInstanceOf(GetPatientAnimalLink::class, $link);
        self::assertSame(['clinic-1', 'patient-1'], [$link->clinicId, $link->patientId]);

        $bus->answer(GetPatientAnimalLink::class, new PatientAnimalLinkDto('patient-2', null, 'Chat tigré', 'Chat', 'Tigré'));
        self::assertNull($visits->animalOfPatient('clinic-1', 'patient-2'));
    }

    public function testAnUnknownOrMalformedVisitOrPatientAnswersNull(): void
    {
        $unknown = new QueryBusVisitPatients(new AnsweringQueryBus());
        self::assertNull($unknown->patientOfAdmission('clinic-1', 'adm-1'));
        self::assertNull($unknown->animalOfPatient('clinic-1', 'patient-1'));

        $malformed = static fn (): never => throw new \InvalidArgumentException('Malformed id');
        $refusing  = new QueryBusVisitPatients(new AnsweringQueryBus([GetAdmissionVisit::class => $malformed, GetPatientAnimalLink::class => $malformed]));
        self::assertNull($refusing->patientOfAdmission('clinic-1', 'not-a-uuid'));
        self::assertNull($refusing->animalOfPatient('clinic-1', 'not-a-uuid'));
    }

    /**
     * @param ?\Closure(object): mixed $answer
     */
    private static function commandBus(?\Closure $answer = null): AnsweringCommandBus
    {
        return new AnsweringCommandBus($answer ?? static fn (): null => null);
    }

    private static function invoice(string $status, ?string $payerClientId = 'client-1', ?string $number = 'FAC-2026-000012', ?string $issuedAt = self::ISSUED_AT): InvoiceDetails
    {
        return new InvoiceDetails(
            id: 'inv-1',
            clinicId: 'clinic-1',
            number: $number,
            status: $status,
            statusLabel: $status,
            admissionId: 'adm-1',
            payerClientId: $payerClientId,
            payerName: 'Sophie Martin',
            payerAddressLines: [],
            lines: [],
            payments: [],
            taxBreakdown: [],
            currency: 'EUR',
            totalNetBeforeDiscountMinorUnits: 10000,
            discountMinorUnits: 0,
            discountKind: null,
            discountValue: null,
            discountReason: null,
            totalNetMinorUnits: 10000,
            totalTaxMinorUnits: 2000,
            totalGrossMinorUnits: 12000,
            amountPaidMinorUnits: 0,
            balanceDueMinorUnits: 12000,
            notes: null,
            createdAt: new \DateTimeImmutable('2026-10-16 08:00:00'),
            issuedAt: null === $issuedAt ? null : new \DateTimeImmutable($issuedAt),
            paidAt: null,
            cancelledAt: null,
            cancellationReason: null,
        );
    }

    private static function prescription(string $status, ?string $ownerClientId = 'client-1', ?string $number = 'ORD-2026-000391', ?string $issuedAt = self::ISSUED_AT): PrescriptionDetails
    {
        return new PrescriptionDetails(
            id: 'rx-1',
            clinicId: 'clinic-1',
            consultationId: 'cons-1',
            number: $number,
            status: $status,
            statusLabel: $status,
            isExpired: false,
            isDispensable: true,
            prescriberName: 'Dr Claire Lambert',
            prescriberRegistrationNumber: null,
            patientName: 'Milo',
            patientSpecies: 'Chien',
            patientBreed: null,
            patientBirthDate: null,
            patientMicrochipNumber: null,
            ownerClientId: $ownerClientId,
            ownerName: 'Sophie Martin',
            ownerAddressLines: [],
            lines: [new PrescriptionLineView('line-1', null, 'Previcox 57 mg', null, [], '1 comprimé par jour pendant 10 jours', null, null, 10, null, '1 boîte', 'NONE', 'Aucune', false, null, null)],
            renewals: [],
            renewalsAllowed: 0,
            renewalsUsed: 0,
            renewalsRemaining: 0,
            carriesControlledSubstance: false,
            validityDays: 365,
            issuedAt: null === $issuedAt ? null : new \DateTimeImmutable($issuedAt),
            validUntil: null,
            notes: null,
            createdAt: new \DateTimeImmutable('2026-10-16 08:00:00'),
            cancelledAt: null,
            cancellationReason: null,
            prescriberId: 'user-1',
            patientAnimalId: 'animal-1',
            patientWeightKg: null,
            renewableByOwner: true,
        );
    }
}
