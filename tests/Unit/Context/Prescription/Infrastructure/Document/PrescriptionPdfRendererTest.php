<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Prescription\Infrastructure\Document;

use App\Context\Prescription\Application\Port\ClinicDocumentIdentityInterface;
use App\Context\Prescription\Application\Query\GetPrescriptionDetails\GetPrescriptionDetails;
use App\Context\Prescription\Application\Query\GetPrescriptionDetails\PrescriptionDetails;
use App\Context\Prescription\Infrastructure\Document\PrescriptionPdfRenderer;
use App\Shared\Application\Document\DocumentClinic;
use App\Tests\Support\AnsweringQueryBus;
use App\Tests\Support\Document\CapturingDocumentPdfRenderer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PrescriptionPdfRenderer::class)]
final class PrescriptionPdfRendererTest extends TestCase
{
    private const string CLINIC_ID       = '01960000-0000-7000-8000-000000000001';
    private const string PRESCRIPTION_ID = '01960000-0000-7000-8000-000000000702';

    public function testAnOrdonnanceIsPrintedWithTheClinicThePatientAndTheOwner(): void
    {
        $documents    = new CapturingDocumentPdfRenderer();
        $prescription = self::prescription('ORD-2026-000001');
        $bus          = new AnsweringQueryBus([GetPrescriptionDetails::class => $prescription]);

        $rendered = new PrescriptionPdfRenderer($bus, self::clinics(), $documents)->prescription(self::CLINIC_ID, self::PRESCRIPTION_ID);

        self::assertSame('ORD-2026-000001.pdf', $rendered->filename);
        self::assertSame('%PDF clinic/document/prescription.html.twig', $rendered->content);

        $last = $documents->last();
        self::assertSame($prescription, $last['context']['prescription']);
        self::assertSame('Ordonnance · ORD-2026-000001', $last['footer']->reference);
        self::assertSame('Milo / Sophie Dupont', $last['footer']->subject);
        self::assertSame('Clinique Montmartre', $last['footer']->clinic?->name);

        $query = $bus->asked[0];
        self::assertInstanceOf(GetPrescriptionDetails::class, $query);
        self::assertSame(self::PRESCRIPTION_ID, $query->prescriptionId);
    }

    public function testADraftIsPrintedAsADraft(): void
    {
        $documents = new CapturingDocumentPdfRenderer();
        $bus       = new AnsweringQueryBus([GetPrescriptionDetails::class => self::prescription(null)]);

        $rendered = new PrescriptionPdfRenderer($bus, self::clinics(), $documents)->prescription(self::CLINIC_ID, self::PRESCRIPTION_ID);

        self::assertSame('ordonnance.pdf', $rendered->filename);
        self::assertSame('Ordonnance · brouillon', $documents->last()['footer']->reference);
    }

    private static function clinics(): ClinicDocumentIdentityInterface
    {
        return new class implements ClinicDocumentIdentityInterface {
            public function forClinic(string $clinicId): DocumentClinic
            {
                return new DocumentClinic('Clinique Montmartre');
            }
        };
    }

    private static function prescription(?string $number): PrescriptionDetails
    {
        return new PrescriptionDetails(
            id: self::PRESCRIPTION_ID,
            clinicId: self::CLINIC_ID,
            consultationId: '01960000-0000-7000-8000-000000000703',
            number: $number,
            status: null === $number ? 'DRAFT' : 'ISSUED',
            statusLabel: 'Émise',
            isExpired: false,
            isDispensable: true,
            prescriberName: 'Dr Rousseau',
            prescriberRegistrationNumber: 'FR-12345',
            patientName: 'Milo',
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
            issuedAt: null,
            validUntil: null,
            notes: null,
            createdAt: new \DateTimeImmutable('2026-10-22 10:00:00'),
            cancelledAt: null,
            cancellationReason: null,
            prescriberId: '01960000-0000-7000-8000-000000000704',
            patientAnimalId: null,
            patientWeightKg: null,
        );
    }
}
