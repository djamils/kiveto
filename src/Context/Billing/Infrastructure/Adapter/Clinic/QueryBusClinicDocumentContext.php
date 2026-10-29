<?php

declare(strict_types=1);

namespace App\Context\Billing\Infrastructure\Adapter\Clinic;

use App\Context\Billing\Application\Port\ClinicDocumentContext;
use App\Context\Billing\Application\Port\ClinicDocumentContextInterface;
use App\Context\Billing\Application\Port\InvoicePaymentDetails;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\ClinicDto;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\GetClinic;
use App\Context\Clinic\Application\Query\Clinic\GetClinicSettings\ClinicSettingsView;
use App\Context\Clinic\Application\Query\Clinic\GetClinicSettings\GetClinicSettings;
use App\Shared\Application\Bus\QueryBusInterface;

/**
 * The clinic's letterhead (GetClinic) and its billing settings
 * (GetClinicSettings), as billing documents print them.
 */
final readonly class QueryBusClinicDocumentContext implements ClinicDocumentContextInterface
{
    public function __construct(private QueryBusInterface $queryBus)
    {
    }

    public function forClinic(string $clinicId): ClinicDocumentContext
    {
        $clinic = $this->queryBus->ask(new GetClinic($clinicId));

        return new ClinicDocumentContext(
            clinic: $clinic instanceof ClinicDto ? $clinic->toDocumentClinic() : null,
            payment: $this->payment($clinicId),
        );
    }

    private function payment(string $clinicId): ?InvoicePaymentDetails
    {
        $settings = $this->queryBus->ask(new GetClinicSettings($clinicId));

        if (!$settings instanceof ClinicSettingsView) {
            return null;
        }

        $billing = $settings->billing;

        return new InvoicePaymentDetails(
            iban: \is_string($billing['iban'] ?? null) ? mb_trim(chunk_split($billing['iban'], 4, ' ')) : null,
            bic: \is_string($billing['bic'] ?? null) ? $billing['bic'] : null,
            vatOnDebits: true === ($billing['vatOnDebits'] ?? false),
            invoiceFooterNote: \is_string($billing['invoiceFooterNote'] ?? null) ? $billing['invoiceFooterNote'] : null,
        );
    }
}
