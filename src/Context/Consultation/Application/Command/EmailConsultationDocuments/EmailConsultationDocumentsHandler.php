<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Command\EmailConsultationDocuments;

use App\Context\Consultation\Application\Port\ConsultationReportPdfRendererInterface;
use App\Context\Consultation\Application\Port\DocumentsMessage;
use App\Context\Consultation\Application\Port\InvoiceDocumentInterface;
use App\Context\Consultation\Application\Port\NotificationRequesterInterface;
use App\Context\Consultation\Application\Port\PractitionerDirectoryInterface;
use App\Context\Consultation\Application\Port\PrescriptionDocumentsInterface;
use App\Context\Consultation\Application\Port\StoredDocument;
use App\Context\Consultation\Application\Service\Documents\ConsultationDocuments;
use App\Context\Consultation\Application\Service\Documents\ConsultationDocumentsNotSendable;
use App\Context\Consultation\Application\Service\Documents\DocumentLabels;
use App\Context\Consultation\Application\Service\Documents\DocumentSelection;
use App\Shared\Domain\Storage\FileStorageInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Network first, writes last: the report and the ordonnances are rendered
 * (Gotenberg) and stored for this e-mail only, then the invoice PDF Billing
 * keeps is obtained (rendered and recorded at its first send), and only then
 * is Notification asked for the CONSULTATION_DOCUMENTS e-mail with exactly
 * the ticked PDFs (AC 27). Send-only files live under
 * `notification/attachments/<sha256 of the key>/` and a retry overwrites
 * them; the idempotency key makes a replay send nothing more. A request no
 * longer valid (consultation reopened, e-mail removed) sends nothing.
 */
#[AsMessageHandler]
final readonly class EmailConsultationDocumentsHandler
{
    public function __construct(
        private ConsultationDocuments $documents,
        private ConsultationReportPdfRendererInterface $reports,
        private PrescriptionDocumentsInterface $prescriptions,
        private InvoiceDocumentInterface $invoices,
        private PractitionerDirectoryInterface $practitioners,
        private FileStorageInterface $storage,
        private NotificationRequesterInterface $notifications,
    ) {
    }

    public function __invoke(EmailConsultationDocuments $command): void
    {
        try {
            $selection = $this->documents->select($command->clinicId, $command->consultationId, $command->documents);
        } catch (ConsultationDocumentsNotSendable) {
            return;
        }

        $clinicId  = $command->clinicId;
        $key       = \sprintf('consultation:%s:documents:%s', $selection->consultation->consultationId, $command->requestId);
        $folder    = 'notification/attachments/' . hash('sha256', $key) . '/';
        $sendOnly  = [];
        $requester = [];
        $labels    = [];
        $values    = [];

        if ($selection->report) {
            $report     = $this->reports->report($clinicId, $selection->consultation->consultationId);
            $sendOnly[] = new StoredDocument($folder . 'compte-rendu.pdf', $report->filename);
            $this->storage->storeAt($folder . 'compte-rendu.pdf', $report->content);
            $labels[] = 'Compte rendu';
            $values[] = 'Joint';
        }

        foreach ($selection->prescriptions as $prescriptionId => $number) {
            $sendOnly[] = $this->prescriptions->store($clinicId, $prescriptionId, $folder . 'ordonnance-' . $prescriptionId . '.pdf');
            $labels[]   = 'Ordonnance';
            $values[]   = $number;
        }

        if (null !== $selection->invoice) {
            $requester[] = $this->invoices->sentFile($clinicId, $selection->invoice->id);
            $labels[]    = 'Facture';
            $values[]    = $selection->invoice->number . ' · ' . DocumentLabels::euros($selection->invoice->totalGrossMinorUnits);
        }

        $this->notifications->request(new DocumentsMessage(
            recipientAddress: (string) $selection->owner->email,
            recipientName: $selection->owner->name,
            clinicId: $clinicId,
            clientId: $selection->owner->clientId,
            consultationId: $selection->consultation->consultationId,
            idempotencyKey: $key,
            parameters: $this->parameters($clinicId, $selection, $labels, $values),
            sendOnlyFiles: $sendOnly,
            requesterFiles: $requester,
        ));
    }

    /**
     * @param list<string> $labels
     * @param list<string> $values
     *
     * @return array<string, string|list<string>>
     */
    private function parameters(string $clinicId, DocumentSelection $selection, array $labels, array $values): array
    {
        $startedAt    = new \DateTimeImmutable($selection->consultation->startedAtUtc, new \DateTimeZone('UTC'));
        $practitioner = DocumentLabels::practitioner($this->practitioners->find($clinicId, $selection->consultation->practitionerUserId));

        return [
            'addressee'             => $selection->owner->addressee,
            'animalName'            => $selection->animalName,
            'consultationDateLabel' => DocumentLabels::longDate($startedAt),
            'consultationLabel'     => DocumentLabels::date($startedAt) . (null !== $practitioner ? ' · ' . $practitioner : ''),
            'documentLabels'        => $labels,
            'documentValues'        => $values,
        ];
    }
}
