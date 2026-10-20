<?php

declare(strict_types=1);

namespace App\Fixtures\System\ElectronicInvoicing\Story;

use App\Context\Billing\Application\Command\RecordTransmissionStatus\RecordTransmissionStatus;
use App\Context\Billing\Application\Command\RequestDocumentTransmission\RequestDocumentTransmission;
use App\Fixtures\Context\Billing\Story\ProfessionalInvoicesStory;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use App\System\ElectronicInvoicing\Application\Command\OpenPlatformConnection\OpenPlatformConnection;
use App\System\ElectronicInvoicing\Application\Command\SyncAllPlatforms\SyncAllPlatforms;
use App\System\ElectronicInvoicing\Application\Command\VerifyPlatformConnection\VerifyPlatformConnection;
use App\System\ElectronicInvoicing\Application\Query\GetTransmissionStatus\GetTransmissionStatus;
use App\System\ElectronicInvoicing\Application\Query\GetTransmissionStatus\TransmissionStatusView;
use App\System\ElectronicInvoicing\Infrastructure\Platform\Simulator\SimulatorDriver;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Worker;
use Zenstruck\Foundry\Story;

/**
 * The e-invoicing demo: the Paris clinic's company (SIREN 812456903) is
 * connected to the local platform simulator and every professional document
 * has gone as far as its buyer's scenario takes it — Approuvée, Rejetée,
 * Refusée (its credit note « Non transmis »), En litige, Irrecevable,
 * « Déposée — client sans plateforme », and one stopped at « Reçue ». Lyon and
 * Cassis (SIREN 901122333) stay unconnected: « Plateforme non connectée ».
 *
 * Nothing waits for a worker: the platform calls queued on the external
 * transports are consumed here, the simulator is played by hand, and Billing's
 * projection is written from the current status of each document.
 */
final class ElectronicInvoicingDemoStory extends Story
{
    private const string PARIS_SIREN = '812456903';

    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly QueryBusInterface $queryBus,
        private readonly SimulatorDriver $simulator,
        private readonly Connection $connection,
        #[Autowire(service: 'messenger.routable_message_bus')]
        private readonly MessageBusInterface $routableBus,
        #[Autowire(service: 'messenger.transport.async_external')]
        private readonly ReceiverInterface $external,
        #[Autowire(service: 'messenger.transport.async_external_batch')]
        private readonly ReceiverInterface $externalBatch,
    ) {
    }

    public function build(): void
    {
        $documents = $this->professionalDocuments();
        $later     = static fn (array $document): bool => 'credit_note' === $document['kind'] || ProfessionalInvoicesStory::RECEIVED_INVOICE_ID === $document['documentId'];

        // Every invoice but the late one goes all the way.
        $this->request(array_filter($documents, static fn (array $document): bool => !$later($document)));
        $this->commandBus->dispatch(new OpenPlatformConnection(self::PARIS_SIREN));
        $this->commandBus->dispatch(new VerifyPlatformConnection(self::PARIS_SIREN));
        $this->consume($this->external);
        $this->simulator->advanceAll();
        $this->synchronise();

        // Then the credit note of the refused invoice (not required any more)
        // and yesterday's invoice, stopped once received.
        $this->request(array_filter($documents, $later));
        $this->consume($this->external);
        // The deposit already played 200: one more step is 202 « Reçue ».
        $this->simulator->advanceAll(maxSteps: 1);
        $this->synchronise();

        // The simulator's own delayed steps would carry on behind the demo's back.
        $this->connection->executeStatement("DELETE FROM shared__messenger_messages WHERE body LIKE '%AdvanceSimulatedFlow%'");

        foreach ($documents as $document) {
            $this->project($document['documentId']);
        }
    }

    /**
     * @param array<array{clinicId: string, documentId: string, kind: string}> $documents
     */
    private function request(array $documents): void
    {
        foreach ($documents as $document) {
            $this->commandBus->dispatch(new RequestDocumentTransmission($document['clinicId'], $document['documentId'], $document['kind']));
        }
    }

    private function synchronise(): void
    {
        $this->commandBus->dispatch(new SyncAllPlatforms());
        $this->consume($this->externalBatch);
    }

    private function project(string $documentId): void
    {
        $view = $this->queryBus->ask(new GetTransmissionStatus($documentId));
        \assert($view instanceof TransmissionStatusView);

        $this->commandBus->dispatch(new RecordTransmissionStatus(
            documentId: $view->documentId,
            sequence: $view->sequence,
            state: $view->state,
            blockReason: $view->blockReason,
            statusCode: $view->statusCode,
            statusLabel: $view->statusLabel,
            reasonCode: $view->reasonCode,
            reasonText: $view->reasonText,
            requiresAction: $view->requiresAction,
            occurredAt: new \DateTimeImmutable()->format(\DateTimeInterface::ATOM),
        ));
    }

    private function consume(ReceiverInterface $receiver): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(WorkerRunningEvent::class, static function (WorkerRunningEvent $event): void {
            if ($event->isWorkerIdle()) {
                $event->getWorker()->stop();
            }
        });

        new Worker([$receiver], $this->routableBus, $dispatcher)->run(['sleep' => 0]);
    }

    /**
     * Every issued professional document, oldest first.
     *
     * @return list<array{clinicId: string, documentId: string, kind: string}>
     */
    private function professionalDocuments(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT BIN_TO_UUID(clinic_id) AS clinic_id, BIN_TO_UUID(id) AS id, 'invoice' AS kind, issued_at
             FROM billing__invoices WHERE processing_rule = 'B2B' AND number IS NOT NULL
             UNION ALL
             SELECT BIN_TO_UUID(clinic_id), BIN_TO_UUID(id), 'credit_note', issued_at
             FROM billing__credit_notes WHERE processing_rule = 'B2B'
             ORDER BY issued_at",
        );

        return array_map(static fn (array $row): array => [
            'clinicId'   => RowAccessor::string($row, 'clinic_id'),
            'documentId' => RowAccessor::string($row, 'id'),
            'kind'       => RowAccessor::string($row, 'kind'),
        ], $rows);
    }
}
