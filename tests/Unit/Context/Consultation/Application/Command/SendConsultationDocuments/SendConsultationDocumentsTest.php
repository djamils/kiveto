<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Consultation\Application\Command\SendConsultationDocuments;

use App\Context\Consultation\Application\Command\EmailConsultationDocuments\EmailConsultationDocuments;
use App\Context\Consultation\Application\Command\EmailConsultationDocuments\EmailConsultationDocumentsHandler;
use App\Context\Consultation\Application\Command\SendConsultationDocuments\ConsultationDocumentsQueued;
use App\Context\Consultation\Application\Command\SendConsultationDocuments\SendConsultationDocuments;
use App\Context\Consultation\Application\Command\SendConsultationDocuments\SendConsultationDocumentsHandler;
use App\Context\Consultation\Application\Port\ClientContact;
use App\Context\Consultation\Application\Port\ClientContactInterface;
use App\Context\Consultation\Application\Port\ClinicDocumentIdentityInterface;
use App\Context\Consultation\Application\Port\ConsultationInvoice;
use App\Context\Consultation\Application\Port\ConsultationReadRepositoryInterface;
use App\Context\Consultation\Application\Port\ConsultationReportPdfRendererInterface;
use App\Context\Consultation\Application\Port\DocumentJobsInterface;
use App\Context\Consultation\Application\Port\DocumentsMessage;
use App\Context\Consultation\Application\Port\InvoiceDocumentInterface;
use App\Context\Consultation\Application\Port\NotificationRequesterInterface;
use App\Context\Consultation\Application\Port\PatientSnapshotProviderInterface;
use App\Context\Consultation\Application\Port\PractitionerDirectoryInterface;
use App\Context\Consultation\Application\Port\PrescriptionDocumentsInterface;
use App\Context\Consultation\Application\Port\ReportOwner;
use App\Context\Consultation\Application\Port\ReportPatient;
use App\Context\Consultation\Application\Port\ReportPatientSnapshot;
use App\Context\Consultation\Application\Port\ReportPractitioner;
use App\Context\Consultation\Application\Port\StoredDocument;
use App\Context\Consultation\Application\Query\GetConsultationDetails\ConsultationDetailsDTO;
use App\Context\Consultation\Application\Service\Documents\ConsultationDocuments;
use App\Context\Consultation\Application\Service\Documents\ConsultationDocumentsNotSendable;
use App\Context\Consultation\Application\Service\Documents\DocumentLabels;
use App\Shared\Application\Document\DocumentClinic;
use App\Shared\Application\Document\RenderedDocument;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Tests\Unit\Context\Consultation\Support\DocumentsRecorder;
use App\Tests\Unit\System\Notification\Support\InMemoryFiles;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;

/**
 * « Envoyer par e-mail » (AC 27): the click checks and queues; the worker
 * renders exactly the ticked PDFs and asks Notification for one e-mail.
 */
final class SendConsultationDocumentsTest extends TestCase
{
    private const string CLINIC       = '01960000-0000-7000-8000-0000000c0001';
    private const string CONSULTATION = '01960000-0000-7000-8000-0000000c0002';
    private const string ADMISSION    = '01960000-0000-7000-8000-0000000c0003';
    private const string PATIENT      = '01960000-0000-7000-8000-0000000c0004';
    private const string VET          = '01960000-0000-7000-8000-0000000c0005';
    private const string CLIENT       = '01960000-0000-7000-8000-0000000c0006';
    private const string RX           = '01960000-0000-7000-8000-0000000c0007';
    private const string RX_OTHER     = '01960000-0000-7000-8000-0000000c0008';
    private const string INVOICE      = '01960000-0000-7000-8000-0000000c0009';

    private string $status = 'CLOSED';
    private ?ReportOwner $owner;
    private ?ClientContact $contact;
    private ?ConsultationInvoice $invoice;
    private ?ReportPractitioner $practitioner;

    private DocumentsRecorder $log;

    private string $zone = 'Europe/Paris';

    private TestHandler $logs;

    private InMemoryFiles $storage;

    protected function setUp(): void
    {
        $this->owner        = new ReportOwner(self::CLIENT, 'Sophie Dubois', null, 's.dubois@email.fr', null);
        $this->contact      = new ClientContact(self::CLIENT, 'Sophie Dubois', 'Madame Dubois', 's.dubois@email.fr', false);
        $this->invoice      = new ConsultationInvoice(self::INVOICE, 'FA-2026-0814', 8870);
        $this->practitioner = new ReportPractitioner('Claire Lambert', 'DR');
        $this->storage      = new InMemoryFiles();
        $this->log          = new DocumentsRecorder();
        $this->logs         = new TestHandler();
    }

    public function testTheClickQueuesTheEmailWithTheTickedDocuments(): void
    {
        $queued = $this->request(['report', 'prescription:' . self::RX, 'invoice', 'report']);

        self::assertSame(['request-1', 'Sophie Dubois', 's.dubois@email.fr', 3], [$queued->requestId, $queued->recipientName, $queued->recipientEmail, $queued->documentCount]);
        self::assertSame([[self::CLINIC, self::CONSULTATION, ['report', 'prescription:' . self::RX, 'invoice'], 'request-1']], $this->log->queued);
        self::assertSame([], $this->log->rendered, 'Nothing is rendered while the user waits.');
    }

    public function testTheWorkerSendsExactlyTheTickedPdfs(): void
    {
        $this->request(['report', 'prescription:' . self::RX, 'invoice']);
        $this->work();

        self::assertCount(1, $this->log->messages);
        $message = $this->log->messages[0];
        $key     = 'consultation:' . self::CONSULTATION . ':documents:request-1';
        $folder  = 'notification/attachments/' . hash('sha256', $key) . '/';

        self::assertSame(['s.dubois@email.fr', 'Sophie Dubois', self::CLINIC, self::CLIENT, self::CONSULTATION, $key], [
            $message->recipientAddress, $message->recipientName, $message->clinicId, $message->clientId, $message->consultationId, $message->idempotencyKey,
        ]);
        self::assertSame([
            'addressee'             => 'Madame Dubois',
            'animalName'            => 'Milo',
            'consultationDateLabel' => '16 octobre 2026',
            'consultationLabel'     => '16/10/2026 · Dr Claire Lambert',
            'documentLabels'        => ['Compte rendu', 'Ordonnance', 'Facture'],
            'documentValues'        => ['Joint', 'ORD-2026-0391', 'FA-2026-0814 · 88,70 €'],
        ], $message->parameters);
        self::assertSame([
            [$folder . 'compte-rendu.pdf', 'compte-rendu-Milo-2026-10-16.pdf'],
            [$folder . 'ordonnance-' . self::RX . '.pdf', 'ORD-2026-0391.pdf'],
        ], array_map(static fn (StoredDocument $f): array => [$f->storageKey, $f->filename], $message->sendOnlyFiles));
        self::assertSame([['billing/sent/invoice/' . self::INVOICE . '.pdf', 'FA-2026-0814.pdf']], array_map(static fn (StoredDocument $f): array => [$f->storageKey, $f->filename], $message->requesterFiles));
        self::assertSame('%PDF report', $this->storage->files[$folder . 'compte-rendu.pdf']);
        self::assertSame(['report', 'prescription:' . self::RX, 'invoice:' . self::INVOICE], $this->log->rendered);
    }

    public function testOnlyTheReportWithAnUntitledPractitioner(): void
    {
        $this->practitioner = new ReportPractitioner('Claire Lambert', null);
        $this->request(['report']);
        $this->work();

        $message = $this->log->messages[0];
        self::assertSame(['Compte rendu'], $message->parameters['documentLabels']);
        self::assertSame('16/10/2026 · Claire Lambert', $message->parameters['consultationLabel']);
        self::assertSame([], $message->requesterFiles);
        self::assertCount(1, $message->sendOnlyFiles);
    }

    public function testOnlyTheInvoiceWithoutKnownPractitioner(): void
    {
        $this->practitioner = null;
        $this->request(['invoice']);
        $this->work();

        $message = $this->log->messages[0];
        self::assertSame(['Facture'], $message->parameters['documentLabels']);
        self::assertSame('16/10/2026', $message->parameters['consultationLabel']);
        self::assertSame([], $message->sendOnlyFiles);
        self::assertSame(['invoice:' . self::INVOICE], $this->log->rendered);
    }

    public function testAnOpenConsultationIsRefused(): void
    {
        $this->status = 'OPEN';

        $this->expectExceptionObject(ConsultationDocumentsNotSendable::notClosed());
        $this->request(['report']);
    }

    public function testAnOwnerWithoutEmailIsRefused(): void
    {
        $this->contact = new ClientContact(self::CLIENT, 'Sophie Dubois', 'Madame Dubois', null, false);

        $this->expectExceptionObject(ConsultationDocumentsNotSendable::noEmail());
        $this->request(['report']);
    }

    public function testAnArchivedOwnerIsRefused(): void
    {
        $this->contact = new ClientContact(self::CLIENT, 'Sophie Dubois', 'Madame Dubois', 's.dubois@email.fr', true);

        $this->expectExceptionObject(ConsultationDocumentsNotSendable::noEmail());
        $this->request(['report']);
    }

    public function testAPatientWithoutOwnerIsRefused(): void
    {
        $this->owner = null;

        $this->expectExceptionObject(ConsultationDocumentsNotSendable::noEmail());
        $this->request(['report']);
    }

    public function testAnUnknownClientIsRefused(): void
    {
        $this->contact = null;

        $this->expectExceptionObject(ConsultationDocumentsNotSendable::noEmail());
        $this->request(['report']);
    }

    public function testNothingTickedIsRefused(): void
    {
        $this->expectExceptionObject(ConsultationDocumentsNotSendable::nothingSelected());
        $this->request([]);
    }

    public function testAnOrdonnanceOfAnotherConsultationIsRefused(): void
    {
        $this->expectExceptionObject(ConsultationDocumentsNotSendable::foreignDocument());
        $this->request(['prescription:' . self::RX_OTHER]);
    }

    public function testAnUnknownDocumentIsRefused(): void
    {
        $this->expectExceptionObject(ConsultationDocumentsNotSendable::foreignDocument());
        $this->request(['etiq']);
    }

    public function testAVisitWithoutIssuedInvoiceIsRefused(): void
    {
        $this->invoice = null;

        $this->expectExceptionObject(ConsultationDocumentsNotSendable::foreignDocument());
        $this->request(['invoice']);
    }

    public function testTheWorkerSendsNothingWhenTheOwnerLostTheirEmailAndSaysSoInTheLog(): void
    {
        $this->request(['report']);
        $this->contact = null;
        $this->work();

        self::assertSame([], $this->log->messages);
        self::assertSame([], $this->log->rendered);
        self::assertTrue($this->logs->hasWarningThatContains('Consultation documents e-mail dropped'));
        self::assertSame(
            ['clinicId' => self::CLINIC, 'consultationId' => self::CONSULTATION, 'requestId' => 'request-1', 'reason' => 'Aucune adresse e-mail pour ce client.'],
            $this->logs->getRecords()[0]->context,
        );
    }

    public function testTheDatesAreTheClinics(): void
    {
        // Started 16/10 07:30 UTC: still 15/10 in Honolulu.
        $this->zone = 'Pacific/Honolulu';
        $this->request(['report']);
        $this->work();

        self::assertSame(['15 octobre 2026', '15/10/2026 · Dr Claire Lambert'], [
            $this->log->messages[0]->parameters['consultationDateLabel'],
            $this->log->messages[0]->parameters['consultationLabel'],
        ]);
    }

    public function testLabels(): void
    {
        self::assertSame('1 204,00 €', DocumentLabels::euros(120400));
        self::assertSame('1 janvier 2027', DocumentLabels::longDate(new \DateTimeImmutable('2026-12-31 23:30:00 UTC')));
        self::assertSame('Pr Jean Martin', DocumentLabels::practitioner(new ReportPractitioner('Jean Martin', 'PR')));
        self::assertNull(DocumentLabels::practitioner(new ReportPractitioner('  ', 'DR')));
        self::assertNull(DocumentLabels::practitioner(null));
    }

    /**
     * @param list<string> $documents
     */
    private function request(array $documents): ConsultationDocumentsQueued
    {
        $uuids = new class implements UuidGeneratorInterface {
            private int $n = 0;

            public function generate(): string
            {
                return 'request-' . ++$this->n;
            }
        };

        $jobs = new class($this->log) implements DocumentJobsInterface {
            public function __construct(private DocumentsRecorder $log)
            {
            }

            public function emailDocuments(string $clinicId, string $consultationId, array $documents, string $requestId): void
            {
                $this->log->queued[] = [$clinicId, $consultationId, $documents, $requestId];
            }
        };

        return new SendConsultationDocumentsHandler($this->documents(), $jobs, $uuids)(new SendConsultationDocuments(self::CLINIC, self::CONSULTATION, $documents, 'actor'));
    }

    private function work(): void
    {
        foreach ($this->log->queued as [$clinicId, $consultationId, $documents, $requestId]) {
            $this->handler()(new EmailConsultationDocuments($clinicId, $consultationId, $documents, $requestId));
        }
    }

    private function handler(): EmailConsultationDocumentsHandler
    {
        $reports = new class($this->log) implements ConsultationReportPdfRendererInterface {
            public function __construct(private DocumentsRecorder $log)
            {
            }

            public function report(string $clinicId, string $consultationId): RenderedDocument
            {
                $this->log->rendered[] = 'report';

                return new RenderedDocument('compte-rendu-Milo-2026-10-16.pdf', '%PDF report');
            }
        };

        $practitioners = new class($this->practitioner) implements PractitionerDirectoryInterface {
            public function __construct(private ?ReportPractitioner $practitioner)
            {
            }

            public function find(string $clinicId, string $userId): ?ReportPractitioner
            {
                return $this->practitioner;
            }
        };

        $notifications = new class($this->log) implements NotificationRequesterInterface {
            public function __construct(private DocumentsRecorder $log)
            {
            }

            public function request(DocumentsMessage $message): string
            {
                $this->log->messages[] = $message;

                return 'message-1';
            }
        };

        $clinics = new class($this->zone) implements ClinicDocumentIdentityInterface {
            public function __construct(private string $zone)
            {
            }

            public function forClinic(string $clinicId): ?DocumentClinic
            {
                return null;
            }

            public function timeZone(string $clinicId): \DateTimeZone
            {
                return new \DateTimeZone($this->zone);
            }
        };

        return new EmailConsultationDocumentsHandler(
            $this->documents(),
            $reports,
            $this->prescriptions(),
            $this->invoices(),
            $practitioners,
            $this->storage,
            $notifications,
            $clinics,
            new Logger('test', [$this->logs]),
        );
    }

    private function documents(): ConsultationDocuments
    {
        $reads = $this->createStub(ConsultationReadRepositoryInterface::class);
        $reads->method('findById')->willReturn(self::consultation($this->status));

        $owner    = $this->owner;
        $patients = new class($owner) implements PatientSnapshotProviderInterface {
            public function __construct(private ?ReportOwner $owner)
            {
            }

            public function snapshot(string $clinicId, string $patientId): ReportPatientSnapshot
            {
                return new ReportPatientSnapshot(new ReportPatient('Milo', 'Chien'), $this->owner);
            }
        };

        $contacts = new class($this->contact) implements ClientContactInterface {
            public function __construct(private ?ClientContact $contact)
            {
            }

            public function contactOf(string $clinicId, string $clientId): ?ClientContact
            {
                return $this->contact;
            }
        };

        return new ConsultationDocuments($reads, $patients, $contacts, $this->prescriptions(), $this->invoices());
    }

    private function prescriptions(): PrescriptionDocumentsInterface
    {
        return new class($this->log, $this->storage) implements PrescriptionDocumentsInterface {
            public function __construct(private DocumentsRecorder $log, private InMemoryFiles $storage)
            {
            }

            public function printable(string $clinicId, string $consultationId): array
            {
                return ['01960000-0000-7000-8000-0000000c0007' => 'ORD-2026-0391'];
            }

            public function store(string $clinicId, string $prescriptionId, string $storageKey): StoredDocument
            {
                $this->log->rendered[] = 'prescription:' . $prescriptionId;
                $this->storage->storeAt($storageKey, '%PDF ordonnance');

                return new StoredDocument($storageKey, 'ORD-2026-0391.pdf');
            }
        };
    }

    private function invoices(): InvoiceDocumentInterface
    {
        return new class($this->invoice, $this->log) implements InvoiceDocumentInterface {
            public function __construct(private ?ConsultationInvoice $invoice, private DocumentsRecorder $log)
            {
            }

            public function invoiceOf(string $clinicId, string $admissionId): ?ConsultationInvoice
            {
                return $this->invoice;
            }

            public function sentFile(string $clinicId, string $invoiceId): StoredDocument
            {
                $this->log->rendered[] = 'invoice:' . $invoiceId;

                return new StoredDocument('billing/sent/invoice/' . $invoiceId . '.pdf', 'FA-2026-0814.pdf');
            }
        };
    }

    private static function consultation(string $status): ConsultationDetailsDTO
    {
        return new ConsultationDetailsDTO(
            consultationId: self::CONSULTATION,
            clinicId: self::CLINIC,
            practitionerUserId: self::VET,
            status: $status,
            appointmentId: null,
            admissionId: self::ADMISSION,
            patientId: self::PATIENT,
            chiefComplaint: 'Boiterie',
            vitals: null,
            notes: [],
            acts: [],
            summary: null,
            startedAtUtc: '2026-10-16 07:30:00',
            closedAtUtc: 'CLOSED' === $status ? '2026-10-16 08:00:00' : null,
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
