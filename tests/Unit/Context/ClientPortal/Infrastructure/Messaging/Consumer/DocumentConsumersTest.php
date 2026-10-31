<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Infrastructure\Messaging\Consumer;

use App\Context\Billing\Domain\Event\InvoiceCancelledIntegrationEvent;
use App\Context\Billing\Domain\Event\InvoiceIssuedForPortalIntegrationEvent;
use App\Context\ClientPortal\Application\Command\BackfillDocumentPets\BackfillDocumentPets;
use App\Context\ClientPortal\Application\Command\PublishInvoice\PublishInvoice;
use App\Context\ClientPortal\Application\Command\PublishPrescription\PublishPrescription;
use App\Context\ClientPortal\Application\Command\ReleaseAwaitingDocuments\ReleaseAwaitingDocuments;
use App\Context\ClientPortal\Application\Command\RepointDocumentPatient\RepointDocumentPatient;
use App\Context\ClientPortal\Application\Command\WithdrawPublishedDocument\WithdrawPublishedDocument;
use App\Context\ClientPortal\Domain\Document\DocumentType;
use App\Context\ClientPortal\Domain\Event\ClientLinkConfirmedIntegrationEvent;
use App\Context\ClientPortal\Infrastructure\Messaging\Consumer\ClientLinkConfirmedDocumentsConsumer;
use App\Context\ClientPortal\Infrastructure\Messaging\Consumer\InvoiceCancelledIntegrationEventConsumer;
use App\Context\ClientPortal\Infrastructure\Messaging\Consumer\InvoiceIssuedForPortalIntegrationEventConsumer;
use App\Context\ClientPortal\Infrastructure\Messaging\Consumer\PatientLinkedToAnimalIntegrationEventConsumer;
use App\Context\ClientPortal\Infrastructure\Messaging\Consumer\PatientReconciledIntoIntegrationEventConsumer;
use App\Context\ClientPortal\Infrastructure\Messaging\Consumer\PrescriptionCancelledIntegrationEventConsumer;
use App\Context\ClientPortal\Infrastructure\Messaging\Consumer\PrescriptionIssuedIntegrationEventConsumer;
use App\Context\Patient\Domain\Event\PatientLinkedToAnimalIntegrationEvent;
use App\Context\Patient\Domain\Event\PatientReconciledIntoIntegrationEvent;
use App\Context\Prescription\Domain\Event\PrescriptionCancelledIntegrationEvent;
use App\Context\Prescription\Domain\Event\PrescriptionIssuedIntegrationEvent;
use App\Tests\Unit\Context\ClientPortal\Support\AnsweringCommandBus;
use PHPUnit\Framework\TestCase;

final class DocumentConsumersTest extends TestCase
{
    private AnsweringCommandBus $bus;

    protected function setUp(): void
    {
        $this->bus = new AnsweringCommandBus(static fn (object $command): null => null);
    }

    public function testAnIssuedInvoiceIsOfferedToThePortal(): void
    {
        $event = new InvoiceIssuedForPortalIntegrationEvent('invoice-1', 'clinic-1');
        new InvoiceIssuedForPortalIntegrationEventConsumer($this->bus)($event);

        $command = $this->only();
        self::assertInstanceOf(PublishInvoice::class, $command);
        self::assertSame(['clinic-1', 'invoice-1'], [$command->clinicId, $command->invoiceId]);
    }

    public function testACancelledInvoiceIsWithdrawn(): void
    {
        $event = new InvoiceCancelledIntegrationEvent('invoice-1', 'clinic-1');
        new InvoiceCancelledIntegrationEventConsumer($this->bus)($event);

        $command = $this->only();
        self::assertInstanceOf(WithdrawPublishedDocument::class, $command);
        self::assertSame(
            ['clinic-1', DocumentType::INVOICE, 'invoice-1'],
            [$command->clinicId, $command->type, $command->sourceRef],
        );
    }

    public function testAnIssuedPrescriptionIsOfferedToThePortal(): void
    {
        $event = new PrescriptionIssuedIntegrationEvent('rx-1', 'clinic-1');
        new PrescriptionIssuedIntegrationEventConsumer($this->bus)($event);

        $command = $this->only();
        self::assertInstanceOf(PublishPrescription::class, $command);
        self::assertSame(['clinic-1', 'rx-1'], [$command->clinicId, $command->prescriptionId]);
    }

    public function testACancelledPrescriptionIsWithdrawn(): void
    {
        $event = new PrescriptionCancelledIntegrationEvent('rx-1', 'clinic-1');
        new PrescriptionCancelledIntegrationEventConsumer($this->bus)($event);

        $command = $this->only();
        self::assertInstanceOf(WithdrawPublishedDocument::class, $command);
        self::assertSame(
            ['clinic-1', DocumentType::PRESCRIPTION, 'rx-1'],
            [$command->clinicId, $command->type, $command->sourceRef],
        );
    }

    public function testAPatientLinkedToAnAnimalBackfillsItsDocuments(): void
    {
        $event = new PatientLinkedToAnimalIntegrationEvent('patient-1', 'animal-1', 'clinic-1');
        new PatientLinkedToAnimalIntegrationEventConsumer($this->bus)($event);

        $command = $this->only();
        self::assertInstanceOf(BackfillDocumentPets::class, $command);
        self::assertSame(
            ['clinic-1', 'patient-1', 'animal-1'],
            [$command->clinicId, $command->patientId, $command->animalId],
        );
    }

    public function testAMergedPatientsDocumentsFollowTheTarget(): void
    {
        $event = new PatientReconciledIntoIntegrationEvent('patient-1', 'patient-2', 'clinic-1');
        new PatientReconciledIntoIntegrationEventConsumer($this->bus)($event);

        $command = $this->only();
        self::assertInstanceOf(RepointDocumentPatient::class, $command);
        self::assertSame(
            ['clinic-1', 'patient-1', 'patient-2'],
            [$command->clinicId, $command->sourcePatientId, $command->targetPatientId],
        );
    }

    public function testAConfirmedLinkReleasesTheWaitingDocuments(): void
    {
        $event = new ClientLinkConfirmedIntegrationEvent('link-1', 'user-1', 'clinic-1', 'client-1');
        new ClientLinkConfirmedDocumentsConsumer($this->bus)($event);

        $command = $this->only();
        self::assertInstanceOf(ReleaseAwaitingDocuments::class, $command);
        self::assertSame(['clinic-1', 'client-1'], [$command->clinicId, $command->clientId]);
    }

    private function only(): object
    {
        self::assertCount(1, $this->bus->commands);

        return $this->bus->commands[0];
    }
}
