<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Document;

use App\Context\Billing\Application\Query\GetInvoiceDetails\GetInvoiceDetails;
use App\Context\Billing\Application\Query\GetInvoiceDetails\InvoiceDetails;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\ClinicDto;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\GetClinic;
use App\Context\Clinic\Application\Query\Clinic\GetClinicSettings\ClinicSettingsView;
use App\Context\Clinic\Application\Query\Clinic\GetClinicSettings\GetClinicSettings;
use App\Presentation\Clinic\ViewModel\Document\DocumentPdfFactory;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/invoices/{invoiceId}/pdf', name: 'clinic_invoice_pdf', methods: ['GET'])]
final class InvoicePdfController extends AbstractController
{
    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly DocumentPdfFactory $documents,
    ) {
    }

    public function __invoke(string $invoiceId): Response
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        try {
            $invoice = $this->queryBus->ask(new GetInvoiceDetails(
                clinicId: $currentClinicId->toString(),
                invoiceId: $invoiceId,
            ));
        } catch (\DomainException) {
            throw $this->createNotFoundException('Facture introuvable.');
        }

        \assert($invoice instanceof InvoiceDetails);

        $clinic = $this->queryBus->ask(new GetClinic($currentClinicId->toString()));
        $clinic = $clinic instanceof ClinicDto ? $clinic : null;

        $pdf = $this->documents->render(
            'clinic/document/invoice.html.twig',
            ['invoice' => $invoice, 'billing' => $this->billing($currentClinicId->toString())],
            $clinic,
            \sprintf('Facture · %s', $invoice->number ?? 'brouillon'),
            $invoice->payerName,
        );

        return $this->documents->response($pdf, $invoice->number ?? 'facture');
    }

    /**
     * What the clinic set about its invoices that is printed at their foot.
     *
     * @return array{iban: string|null, bic: string|null, vatOnDebits: bool, invoiceFooterNote: string|null}|null
     */
    private function billing(string $clinicId): ?array
    {
        $settings = $this->queryBus->ask(new GetClinicSettings($clinicId));

        if (!$settings instanceof ClinicSettingsView) {
            return null;
        }

        $b = $settings->billing;

        return [
            'iban'              => \is_string($b['iban'] ?? null) ? mb_trim(chunk_split($b['iban'], 4, ' ')) : null,
            'bic'               => \is_string($b['bic'] ?? null) ? $b['bic'] : null,
            'vatOnDebits'       => true === ($b['vatOnDebits'] ?? false),
            'invoiceFooterNote' => \is_string($b['invoiceFooterNote'] ?? null) ? $b['invoiceFooterNote'] : null,
        ];
    }
}
