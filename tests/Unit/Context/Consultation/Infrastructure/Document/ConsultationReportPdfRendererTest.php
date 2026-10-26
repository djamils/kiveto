<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Consultation\Infrastructure\Document;

use App\Context\Consultation\Application\Port\ClinicDocumentIdentityInterface;
use App\Context\Consultation\Application\Port\PatientSnapshotProviderInterface;
use App\Context\Consultation\Application\Port\PractitionerDirectoryInterface;
use App\Context\Consultation\Application\Port\ReportOwner;
use App\Context\Consultation\Application\Port\ReportPatient;
use App\Context\Consultation\Application\Port\ReportPatientSnapshot;
use App\Context\Consultation\Application\Port\ReportPractitioner;
use App\Context\Consultation\Application\Query\GetConsultationDetails\ConsultationDetailsDTO;
use App\Context\Consultation\Application\Query\GetConsultationDetails\GetConsultationDetails;
use App\Context\Consultation\Infrastructure\Document\ConsultationReportPdfRenderer;
use App\Shared\Application\Document\DocumentClinic;
use App\Tests\Support\AnsweringQueryBus;
use App\Tests\Support\Document\CapturingDocumentPdfRenderer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConsultationReportPdfRenderer::class)]
#[CoversClass(ReportPatientSnapshot::class)]
#[CoversClass(ReportPatient::class)]
#[CoversClass(ReportOwner::class)]
#[CoversClass(ReportPractitioner::class)]
final class ConsultationReportPdfRendererTest extends TestCase
{
    private const string CLINIC_ID       = '01960000-0000-7000-8000-000000000001';
    private const string CONSULTATION_ID = '01960000-0000-7000-8000-0000000c0001';
    private const string VET_ID          = '01960000-0000-7000-8000-0000000c0002';

    public function testAClosedConsultationIsPrintedWithItsPatientOwnerAndSigningVeterinarian(): void
    {
        $documents    = new CapturingDocumentPdfRenderer();
        $consultation = self::consultation('2026-10-22 08:30:00');
        $bus          = new AnsweringQueryBus([GetConsultationDetails::class => $consultation]);
        $owner        = new ReportOwner('client-1', 'Sophie Garnier', '+33612345678', 'sophie@example.fr', '18 rue des Abbesses 75018 Paris');

        $rendered = $this->renderer($bus, $documents, $owner)->report(self::CLINIC_ID, self::CONSULTATION_ID);

        self::assertSame('compte-rendu-Milo-2026-10-22.pdf', $rendered->filename);
        self::assertSame('%PDF clinic/document/report.html.twig', $rendered->content);

        $last = $documents->last();
        self::assertSame($consultation, $last['context']['consultation']);
        self::assertSame('Milo', self::patient($last['context']['patient'])->name);
        self::assertSame($owner, $last['context']['owner']);
        $practitioner = $last['context']['practitioner'];
        self::assertInstanceOf(ReportPractitioner::class, $practitioner);
        self::assertSame('Claire Lambert', $practitioner->displayName);
        self::assertSame('DR', $practitioner->professionalTitle);
        $startedAt = $last['context']['startedAt'];
        $closedAt  = $last['context']['closedAt'];
        self::assertInstanceOf(\DateTimeImmutable::class, $startedAt);
        self::assertInstanceOf(\DateTimeImmutable::class, $closedAt);
        self::assertSame('2026-10-22T08:30:00+00:00', $startedAt->format(\DATE_ATOM));
        self::assertSame('2026-10-22T09:00:00+00:00', $closedAt->format(\DATE_ATOM));
        self::assertSame('Compte rendu de consultation', $last['footer']->reference);
        self::assertSame('Milo / Sophie Garnier', $last['footer']->subject);
        self::assertSame('Clinique Montmartre', $last['footer']->clinic?->name);

        $query = $bus->asked[0];
        self::assertInstanceOf(GetConsultationDetails::class, $query);
        self::assertSame(self::CONSULTATION_ID, $query->consultationId);
    }

    public function testAnOpenConsultationOfAnAnimalWithoutOwnerNamesOnlyThePatient(): void
    {
        $documents = new CapturingDocumentPdfRenderer();
        $bus       = new AnsweringQueryBus([GetConsultationDetails::class => self::consultation(null)]);

        $this->renderer($bus, $documents, null)->report(self::CLINIC_ID, self::CONSULTATION_ID);

        $last = $documents->last();
        self::assertNull($last['context']['closedAt']);
        self::assertNull($last['context']['owner']);
        self::assertSame('Milo', $last['footer']->subject);
    }

    public function testAnUnknownConsultationIsRefusedBeforeAnythingIsRendered(): void
    {
        $documents = new CapturingDocumentPdfRenderer();
        $bus       = new AnsweringQueryBus([GetConsultationDetails::class => static fn (): never => throw new \DomainException('Unknown')]);

        $this->expectException(\DomainException::class);

        $this->renderer($bus, $documents, null)->report(self::CLINIC_ID, self::CONSULTATION_ID);
    }

    private function renderer(AnsweringQueryBus $bus, CapturingDocumentPdfRenderer $documents, ?ReportOwner $owner): ConsultationReportPdfRenderer
    {
        $patients = new class($owner) implements PatientSnapshotProviderInterface {
            public function __construct(private readonly ?ReportOwner $owner)
            {
            }

            public function snapshot(string $clinicId, string $patientId): ReportPatientSnapshot
            {
                return new ReportPatientSnapshot(new ReportPatient('Milo', 'Chien', 'Labrador', '♂', true, '2020-05-01', '6 ans', '250269812345678'), $this->owner);
            }
        };

        $practitioners = new class implements PractitionerDirectoryInterface {
            public function find(string $clinicId, string $userId): ?ReportPractitioner
            {
                return '01960000-0000-7000-8000-0000000c0002' === $userId ? new ReportPractitioner('Claire Lambert', 'DR') : null;
            }
        };

        $clinics = new class implements ClinicDocumentIdentityInterface {
            public function forClinic(string $clinicId): DocumentClinic
            {
                return new DocumentClinic('Clinique Montmartre');
            }
        };

        return new ConsultationReportPdfRenderer($bus, $patients, $practitioners, $clinics, $documents);
    }

    private static function patient(mixed $patient): ReportPatient
    {
        self::assertInstanceOf(ReportPatient::class, $patient);
        self::assertSame('Chien', $patient->speciesLabel);
        self::assertSame('Labrador', $patient->breed);
        self::assertSame('♂', $patient->sexLabel);
        self::assertTrue($patient->sterilized);
        self::assertSame('2020-05-01', $patient->birthDate);
        self::assertSame('6 ans', $patient->ageLabel);
        self::assertSame('250269812345678', $patient->microchip);

        return $patient;
    }

    private static function consultation(?string $startedAt): ConsultationDetailsDTO
    {
        return new ConsultationDetailsDTO(
            consultationId: self::CONSULTATION_ID,
            clinicId: self::CLINIC_ID,
            practitionerUserId: self::VET_ID,
            status: null !== $startedAt ? 'CLOSED' : 'OPEN',
            appointmentId: null,
            admissionId: '01960000-0000-7000-8000-0000000c0003',
            patientId: '01960000-0000-7000-8000-0000000c0004',
            chiefComplaint: 'Boiterie',
            vitals: null,
            notes: [],
            acts: [],
            summary: null,
            startedAtUtc: $startedAt ?? '2026-10-22 08:30:00',
            closedAtUtc: null !== $startedAt ? '2026-10-22 09:00:00' : null,
            subjectiveText: null,
            objectiveObservations: null,
            teamMemo: null,
            motifs: [],
            typedVitals: [],
            examSystems: [],
            diagnoses: [],
            planActions: [],
            prescriptionLines: [],
            billingLines: [],
            totals: ['totalHtMinorUnits' => 0, 'totalTvaMinorUnits' => 0, 'totalTtcMinorUnits' => 0, 'currency' => 'EUR'],
        );
    }
}
