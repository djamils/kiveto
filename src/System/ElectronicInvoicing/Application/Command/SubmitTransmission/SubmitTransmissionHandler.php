<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Application\Command\SubmitTransmission;

use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\ExternalApi\ExternalServiceUnavailable;
use App\System\ElectronicInvoicing\Application\Command\RecordSubmissionPrepared\RecordSubmissionPrepared;
use App\System\ElectronicInvoicing\Application\Command\RecordSubmissionReceipt\RecordSubmissionReceipt;
use App\System\ElectronicInvoicing\Application\Command\RecordTransmissionBlocked\RecordTransmissionBlocked;
use App\System\ElectronicInvoicing\Application\Command\RecordTransmissionNotRequired\RecordTransmissionNotRequired;
use App\System\ElectronicInvoicing\Application\Command\RequestInvoiceTransmission\RequestInvoiceTransmissionHandler;
use App\System\ElectronicInvoicing\Application\Command\RequestInvoiceTransmission\TransmittableElectronicAddress;
use App\System\ElectronicInvoicing\Application\Command\RequestInvoiceTransmission\TransmittableInvoice;
use App\System\ElectronicInvoicing\Application\Exception\RenderingFailedException;
use App\System\ElectronicInvoicing\Application\Port\DocumentStoreInterface;
use App\System\ElectronicInvoicing\Application\Port\InvoiceDocumentRendererInterface;
use App\System\ElectronicInvoicing\Domain\Connection\PlatformConnectionRepositoryInterface;
use App\System\ElectronicInvoicing\Domain\Port\DirectoryLookupInterface;
use App\System\ElectronicInvoicing\Domain\Port\FlowSubmitterInterface;
use App\System\ElectronicInvoicing\Domain\Port\OutboundFlow;
use App\System\ElectronicInvoicing\Domain\Port\PlatformRefusedFlowException;
use App\System\ElectronicInvoicing\Domain\Transmission\BlockReason;
use App\System\ElectronicInvoicing\Domain\Transmission\OutboundTransmission;
use App\System\ElectronicInvoicing\Domain\Transmission\OutboundTransmissionId;
use App\System\ElectronicInvoicing\Domain\Transmission\OutboundTransmissionRepositoryInterface;
use App\System\ElectronicInvoicing\Domain\Transmission\TransmissionKind;
use App\System\ElectronicInvoicing\Domain\Transmission\TransmissionState;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Sends one transmission to its platform, outside any database transaction,
 * writing nothing itself: every outcome is recorded by a command.
 *
 * The first time, the buyer's address is looked up in the platform directory
 * (any failure falls back on scheme 0225 + the buyer SIREN — it never holds
 * the invoice back), the file is rendered, validated and stored, and the
 * transmission is frozen as submitting. A retry reloads that same file.
 * A failure after a successful deposit is retried and resubmits the same
 * bytes, which the platform answers with the same flow id.
 */
#[AsMessageHandler(bus: 'messenger.bus.external_api')]
final readonly class SubmitTransmissionHandler
{
    public function __construct(
        private OutboundTransmissionRepositoryInterface $transmissions,
        private PlatformConnectionRepositoryInterface $connections,
        private DirectoryLookupInterface $directory,
        private InvoiceDocumentRendererInterface $renderer,
        private DocumentStoreInterface $documents,
        private FlowSubmitterInterface $submitter,
        private CommandBusInterface $commandBus,
        #[Autowire(service: 'monolog.logger.external_api')]
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(SubmitTransmission $command): void
    {
        $transmission = $this->transmissions->get(OutboundTransmissionId::fromString($command->transmissionId));
        $connectionId = $transmission->connectionId();

        if (null === $connectionId || !\in_array($transmission->state(), [TransmissionState::PREPARED, TransmissionState::SUBMITTING], true)) {
            return;
        }

        if (!$this->precedingInvoiceAllows($transmission)) {
            return;
        }

        $connection = $this->connections->get($connectionId);

        if (!$connection->organisationId()->equals($transmission->sellerSiren())) {
            $this->logger->error('Electronic invoice not sent: the seller is not the organisation of the connection.', [
                'transmissionId' => $command->transmissionId,
                'connectionId'   => $connectionId->toString(),
            ]);
            $this->block($command->transmissionId, BlockReason::ORGANISATION_MISMATCH, 'The seller SIREN (BT-30) is not the organisation of the platform connection.');

            return;
        }

        $file = $this->file($transmission);

        if (null === $file) {
            return;
        }

        [$content, $sha256] = $file;

        try {
            $receipt = $this->submitter->submit(new OutboundFlow(
                organisationId: $connection->organisationId()->value(),
                trackingId: $transmission->trackingId()->toString(),
                fileName: \sprintf('%s.xml', $transmission->trackingId()->toString()),
                content: $content,
                sha256: $sha256,
                syntax: 'CII',
                processingRule: TransmittableInvoice::fromArray($transmission->payload())->processingRule,
            ));
        } catch (PlatformRefusedFlowException $e) {
            $this->block($command->transmissionId, BlockReason::PLATFORM_REFUSED, $e->reason);

            return;
        }

        $this->commandBus->dispatch(new RecordSubmissionReceipt(
            transmissionId: $command->transmissionId,
            flowId: $receipt->flowId,
            submittedAt: $receipt->submittedAt->format(\DateTimeInterface::ATOM),
        ));
    }

    /**
     * A credit note never reaches the platform before its invoice; it is not
     * sent at all once that invoice is void.
     */
    private function precedingInvoiceAllows(OutboundTransmission $transmission): bool
    {
        $number = $transmission->precedingDocumentNumber();

        if (TransmissionKind::CREDIT_NOTE !== $transmission->kind() || null === $number) {
            return true;
        }

        $invoice = $this->transmissions->byDocument($transmission->sellerSiren(), TransmissionKind::INVOICE, $number);

        if (null !== $invoice && !RequestInvoiceTransmissionHandler::canBeCredited($invoice)) {
            $this->commandBus->dispatch(new RecordTransmissionNotRequired($transmission->id()->toString(), 'The credited invoice is void for the tax authority or was never accepted.'));

            return false;
        }

        // Not yet submitted: it stays requested and the catch-up retries it.
        return true === $invoice?->wasAccepted();
    }

    /**
     * The bytes to send: rendered, validated and stored the first time,
     * reloaded afterwards.
     *
     * @return array{string, string}|null null when the document could not be rendered
     */
    private function file(OutboundTransmission $transmission): ?array
    {
        $document = $transmission->document();

        if (TransmissionState::SUBMITTING === $transmission->state() && null !== $document) {
            return [$this->documents->load($document), $document->sha256()];
        }

        $invoice = TransmittableInvoice::fromArray($transmission->payload());
        $address = $this->buyerAddress($invoice);

        try {
            $content = $this->renderer->render($invoice, $address);
        } catch (RenderingFailedException $e) {
            $this->logger->error('Electronic invoice not rendered: the file does not validate.', [
                'transmissionId' => $transmission->id()->toString(),
                'errors'         => $e->errors,
            ]);
            $this->block($transmission->id()->toString(), BlockReason::RENDERING_FAILED, implode(' | ', $e->errors));

            return null;
        }

        $sha256 = hash('sha256', $content);
        $stored = $this->documents->store($transmission->trackingId()->toString(), $sha256, $content);

        $this->commandBus->dispatch(new RecordSubmissionPrepared(
            transmissionId: $transmission->id()->toString(),
            documentId: $stored->storageId(),
            sha256: $sha256,
            electronicAddressScheme: $address->scheme,
            electronicAddressIdentifier: $address->identifier,
        ));

        return [$content, $sha256];
    }

    /**
     * Where the platform directory routes the buyer; the default address
     * (0225 + SIREN) when it cannot tell.
     */
    private function buyerAddress(TransmittableInvoice $invoice): TransmittableElectronicAddress
    {
        $default = $invoice->buyer->electronicAddress;

        try {
            $entry = $this->directory->lookup($invoice->buyer->siren);
        } catch (ExternalServiceUnavailable) {
            return $default;
        }

        if (!$entry->reachable || null === $entry->scheme || null === $entry->identifier) {
            return $default;
        }

        return new TransmittableElectronicAddress($entry->scheme, $entry->identifier);
    }

    private function block(string $transmissionId, BlockReason $reason, ?string $detail): void
    {
        $this->commandBus->dispatch(new RecordTransmissionBlocked($transmissionId, $reason->value, $detail));
    }
}
