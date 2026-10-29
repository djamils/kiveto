<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Consultation\Infrastructure\Adapter;

use App\Context\Billing\Application\Command\EnsureSentInvoiceFile\EnsureSentInvoiceFile;
use App\Context\Billing\Application\Command\EnsureSentInvoiceFile\SentDocumentFile;
use App\Context\Billing\Application\Query\FindInvoiceForAdmission\AdmissionInvoiceRow;
use App\Context\Billing\Application\Query\FindInvoiceForAdmission\FindInvoiceForAdmission;
use App\Context\Client\Application\Query\GetClientNotificationContact\ClientNotificationContact;
use App\Context\Client\Application\Query\GetClientNotificationContact\GetClientNotificationContact;
use App\Context\Consultation\Application\Command\EmailConsultationDocuments\EmailConsultationDocuments;
use App\Context\Consultation\Application\Port\DocumentsMessage;
use App\Context\Consultation\Application\Port\StoredDocument;
use App\Context\Consultation\Infrastructure\Adapter\Billing\CommandBusInvoiceDocument;
use App\Context\Consultation\Infrastructure\Adapter\Client\QueryBusClientContact;
use App\Context\Consultation\Infrastructure\Adapter\Notification\CommandBusNotificationRequester;
use App\Context\Consultation\Infrastructure\Adapter\Prescription\CommandBusPrescriptionDocuments;
use App\Context\Consultation\Infrastructure\Messaging\MessengerDocumentJobs;
use App\Context\Prescription\Application\Command\RenderPrescriptionFile\PrescriptionFile;
use App\Context\Prescription\Application\Command\RenderPrescriptionFile\RenderPrescriptionFile;
use App\Context\Prescription\Application\Query\ListPrescriptionsForConsultation\ConsultationPrescriptionRow;
use App\Context\Prescription\Application\Query\ListPrescriptionsForConsultation\ListPrescriptionsForConsultation;
use App\Shared\Application\Bus\CommandBusInterface;
use App\System\Notification\Application\Command\RequestNotification\AttachmentReference;
use App\System\Notification\Application\Command\RequestNotification\RequestNotification;
use App\Tests\Support\AnsweringQueryBus;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * The adapters of the documents e-mail: each one only translates between
 * Consultation's ports and the other context's public messages.
 */
final class DocumentsAdaptersTest extends TestCase
{
    public function testTheOwnerIsGreetedWithTheirCivility(): void
    {
        $bus      = new AnsweringQueryBus([GetClientNotificationContact::class => self::contact('Mme', 'Dubois')]);
        $contacts = new QueryBusClientContact($bus);

        $contact = $contacts->contactOf('clinic-1', 'client-1');

        self::assertNotNull($contact);
        self::assertSame(['client-1', 'Sophie Dubois', 'Madame Dubois', 's.dubois@email.fr', false], [$contact->clientId, $contact->name, $contact->addressee, $contact->email, $contact->archived]);
        $query = $bus->asked[0];
        self::assertInstanceOf(GetClientNotificationContact::class, $query);
        self::assertSame(['clinic-1', 'client-1'], [$query->clinicId, $query->clientId]);

        $bus->answer(GetClientNotificationContact::class, self::contact('', 'Dubois'));
        self::assertSame('Sophie Dubois', $contacts->contactOf('clinic-1', 'client-1')?->addressee);

        $bus->answer(GetClientNotificationContact::class, null);
        self::assertNull($contacts->contactOf('clinic-1', 'client-1'));
    }

    public function testTheEmailIsRequestedWithItsOwnedAndBorrowedFiles(): void
    {
        $bus = self::commandBus([RequestNotification::class => 'message-1']);

        $id = new CommandBusNotificationRequester($bus)->request(new DocumentsMessage(
            recipientAddress: 's.dubois@email.fr',
            recipientName: 'Sophie Dubois',
            clinicId: 'clinic-1',
            clientId: 'client-1',
            consultationId: 'consultation-1',
            idempotencyKey: 'consultation:consultation-1:documents:r-1',
            parameters: ['animalName' => 'Milo'],
            sendOnlyFiles: [new StoredDocument('notification/attachments/h/compte-rendu.pdf', 'compte-rendu.pdf')],
            requesterFiles: [new StoredDocument('billing/sent/invoice/i-1.pdf', 'FA-1.pdf')],
        ));

        self::assertSame('message-1', $id);
        $request = $bus->commands[0];
        self::assertInstanceOf(RequestNotification::class, $request);
        self::assertSame(['CONSULTATION_DOCUMENTS', 'EMAIL', 's.dubois@email.fr', 'Sophie Dubois', 'clinic-1', 'client-1', 'consultation', 'consultation-1', 'consultation:consultation-1:documents:r-1', ['animalName' => 'Milo']], [
            $request->purpose, $request->channel, $request->recipientAddress, $request->recipientName, $request->clinicId, $request->clientId,
            $request->subjectType, $request->subjectId, $request->idempotencyKey, $request->parameters,
        ]);
        self::assertSame([
            ['notification/attachments/h/compte-rendu.pdf', 'compte-rendu.pdf', 'application/pdf', AttachmentReference::OWNER_NOTIFICATION],
            ['billing/sent/invoice/i-1.pdf', 'FA-1.pdf', 'application/pdf', AttachmentReference::OWNER_REQUESTER],
        ], array_map(static fn (AttachmentReference $a): array => [$a->storageKey, $a->filename, $a->mime, $a->owner], $request->attachments));
        self::assertNull($request->fallback);
    }

    public function testOnlySignedOrdonnancesArePrintableAndOneIsStoredWhereAsked(): void
    {
        $queries = new AnsweringQueryBus([ListPrescriptionsForConsultation::class => [
            new ConsultationPrescriptionRow('rx-1', 'ORD-1', 'ISSUED', 2),
            new ConsultationPrescriptionRow('rx-2', null, 'DRAFT', 1),
            new ConsultationPrescriptionRow('rx-3', 'ORD-3', 'CANCELLED', 1),
            'not a row',
        ]]);
        $commands      = self::commandBus([RenderPrescriptionFile::class => new PrescriptionFile('k/ordonnance-rx-1.pdf', 'ORD-1.pdf')]);
        $prescriptions = new CommandBusPrescriptionDocuments($queries, $commands);

        self::assertSame(['rx-1' => 'ORD-1'], $prescriptions->printable('clinic-1', 'consultation-1'));
        $query = $queries->asked[0];
        self::assertInstanceOf(ListPrescriptionsForConsultation::class, $query);
        self::assertSame(['clinic-1', 'consultation-1'], [$query->clinicId, $query->consultationId]);

        $file = $prescriptions->store('clinic-1', 'rx-1', 'k/ordonnance-rx-1.pdf');
        self::assertSame(['k/ordonnance-rx-1.pdf', 'ORD-1.pdf'], [$file->storageKey, $file->filename]);
        $command = $commands->commands[0];
        self::assertInstanceOf(RenderPrescriptionFile::class, $command);
        self::assertSame(['clinic-1', 'rx-1', 'k/ordonnance-rx-1.pdf'], [$command->clinicId, $command->prescriptionId, $command->storageKey]);

        $queries->answer(ListPrescriptionsForConsultation::class, null);
        self::assertSame([], $prescriptions->printable('clinic-1', 'consultation-1'));
    }

    public function testTheIssuedInvoiceOfTheVisitAndItsSentFile(): void
    {
        $queries  = new AnsweringQueryBus([FindInvoiceForAdmission::class => new AdmissionInvoiceRow('i-1', 'FA-1', 'ISSUED', 8870, 'EUR')]);
        $commands = self::commandBus([EnsureSentInvoiceFile::class => new SentDocumentFile('billing/sent/invoice/i-1.pdf', 'FA-1.pdf')]);
        $invoices = new CommandBusInvoiceDocument($queries, $commands);

        $invoice = $invoices->invoiceOf('clinic-1', 'admission-1');
        self::assertNotNull($invoice);
        self::assertSame(['i-1', 'FA-1', 8870], [$invoice->id, $invoice->number, $invoice->totalGrossMinorUnits]);
        $query = $queries->asked[0];
        self::assertInstanceOf(FindInvoiceForAdmission::class, $query);
        self::assertSame(['clinic-1', 'admission-1'], [$query->clinicId, $query->admissionId]);

        $file = $invoices->sentFile('clinic-1', 'i-1');
        self::assertSame(['billing/sent/invoice/i-1.pdf', 'FA-1.pdf'], [$file->storageKey, $file->filename]);
        $command = $commands->commands[0];
        self::assertInstanceOf(EnsureSentInvoiceFile::class, $command);
        self::assertSame(['clinic-1', 'i-1'], [$command->clinicId, $command->invoiceId]);

        $queries->answer(FindInvoiceForAdmission::class, new AdmissionInvoiceRow('i-2', null, 'DRAFT', 100, 'EUR'));
        self::assertNull($invoices->invoiceOf('clinic-1', 'admission-1'));
        $queries->answer(FindInvoiceForAdmission::class, new AdmissionInvoiceRow('i-3', 'FA-3', 'CREDITED', 100, 'EUR'));
        self::assertNull($invoices->invoiceOf('clinic-1', 'admission-1'), 'A credited invoice is not e-mailed.');
        $queries->answer(FindInvoiceForAdmission::class, null);
        self::assertNull($invoices->invoiceOf('clinic-1', 'admission-1'));
    }

    public function testTheEmailIsQueuedOnTheRawCommandBus(): void
    {
        $bus = new class implements MessageBusInterface {
            /** @var list<object> */
            public array $messages = [];

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                $this->messages[] = $message;

                return new Envelope($message, $stamps);
            }
        };

        new MessengerDocumentJobs($bus)->emailDocuments('clinic-1', 'consultation-1', ['report', 'invoice'], 'r-1');

        $message = $bus->messages[0];
        self::assertInstanceOf(EmailConsultationDocuments::class, $message);
        self::assertSame(['clinic-1', 'consultation-1', ['report', 'invoice'], 'r-1'], [$message->clinicId, $message->consultationId, $message->documents, $message->requestId]);
    }

    private static function contact(string $civility, string $lastName): ClientNotificationContact
    {
        return new ClientNotificationContact('client-1', 'Sophie Dubois', $civility, 'Sophie', $lastName, 's.dubois@email.fr', null, null, [], false);
    }

    /**
     * @param array<class-string, mixed> $answers
     *
     * @return CommandBusInterface&object{commands: list<object>}
     */
    private static function commandBus(array $answers): CommandBusInterface
    {
        return new class($answers) implements CommandBusInterface {
            /** @var list<object> */
            public array $commands = [];

            /** @param array<class-string, mixed> $answers */
            public function __construct(private readonly array $answers)
            {
            }

            public function dispatch(object $command, object ...$stamps): mixed
            {
                $this->commands[] = $command;

                return $this->answers[$command::class] ?? null;
            }
        };
    }
}
