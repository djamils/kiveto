<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Adapter\Billing;

use App\Context\Billing\Application\Command\EnsureSentInvoiceFile\EnsureSentInvoiceFile;
use App\Context\Billing\Application\Command\EnsureSentInvoiceFile\SentDocumentFile;
use App\Context\Billing\Application\Query\GetInvoiceDetails\GetInvoiceDetails;
use App\Context\Billing\Application\Query\GetInvoiceDetails\InvoiceDetails;
use App\Context\Billing\Application\Query\ListInvoicePortalStatuses\InvoicePortalStatus;
use App\Context\Billing\Application\Query\ListInvoicePortalStatuses\ListInvoicePortalStatuses;
use App\Context\Billing\Application\Query\ListPortalPublishableInvoices\ListPortalPublishableInvoices;
use App\Context\Billing\Application\Query\ListPortalPublishableInvoices\PortalPublishableInvoice;
use App\Context\ClientPortal\Application\Port\InvoiceDocumentsInterface;
use App\Context\ClientPortal\Application\Port\InvoicePortalState;
use App\Context\ClientPortal\Application\Port\InvoicePublication;
use App\Context\ClientPortal\Application\Port\PublishableSource;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;

/** Billing's invoice queries and its durable sent-invoice PDF. */
final readonly class BusInvoiceDocuments implements InvoiceDocumentsInterface
{
    /** Billing's stored status → the Portal's payment status; any other status is not listable. */
    private const array PAYMENT_STATUSES = [
        'ISSUED'         => InvoicePortalState::UNPAID,
        'PARTIALLY_PAID' => InvoicePortalState::PARTIALLY_PAID,
        'PAID'           => InvoicePortalState::PAID,
        'CREDITED'       => InvoicePortalState::CREDITED,
    ];

    public function __construct(
        private QueryBusInterface $queryBus,
        private CommandBusInterface $commandBus,
    ) {
    }

    public function publication(string $clinicId, string $invoiceId): ?InvoicePublication
    {
        try {
            $invoice = $this->queryBus->ask(new GetInvoiceDetails($clinicId, $invoiceId));
        } catch (\DomainException) {
            return null;
        } catch (\InvalidArgumentException) {
            return null;
        }

        if (!$invoice instanceof InvoiceDetails || !\array_key_exists($invoice->status, self::PAYMENT_STATUSES)) {
            return null;
        }

        if (null === $invoice->payerClientId || null === $invoice->number || null === $invoice->issuedAt) {
            return null;
        }

        return new InvoicePublication($invoice->id, $invoice->payerClientId, $invoice->number, $invoice->issuedAt, $invoice->admissionId);
    }

    public function ensureFile(string $clinicId, string $invoiceId): string
    {
        $file = $this->commandBus->dispatch(new EnsureSentInvoiceFile($clinicId, $invoiceId));
        \assert($file instanceof SentDocumentFile);

        return $file->storageKey;
    }

    public function states(string $clinicId, array $invoiceIds): array
    {
        if ([] === $invoiceIds) {
            return [];
        }

        /** @var list<InvoicePortalStatus> $statuses */
        $statuses = $this->queryBus->ask(new ListInvoicePortalStatuses($clinicId, $invoiceIds));
        $states   = [];

        foreach ($statuses as $status) {
            $states[$status->invoiceId] = new InvoicePortalState(
                self::PAYMENT_STATUSES[$status->status] ?? null,
                $status->totalGrossMinorUnits,
                $status->currency,
            );
        }

        return $states;
    }

    public function publishableSince(\DateTimeImmutable $issuedFrom, ?string $afterId, int $limit): array
    {
        /** @var list<PortalPublishableInvoice> $invoices */
        $invoices = $this->queryBus->ask(new ListPortalPublishableInvoices($issuedFrom, $afterId, $limit));

        return array_map(static fn (PortalPublishableInvoice $invoice): PublishableSource => new PublishableSource($invoice->invoiceId, $invoice->clinicId), $invoices);
    }
}
