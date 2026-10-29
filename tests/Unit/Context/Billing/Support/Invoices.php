<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Billing\Support;

use App\Context\Billing\Application\Query\GetInvoiceDetails\InvoiceDetails;

/**
 * Invoice details as Billing reads them, for the dispatch use cases.
 */
final class Invoices
{
    public const string CLINIC    = '01960000-0000-7000-8000-00000000e001';
    public const string INVOICE   = '01960000-0000-7000-8000-00000000e002';
    public const string CLIENT    = '01960000-0000-7000-8000-00000000e003';
    public const string ADMISSION = '01960000-0000-7000-8000-00000000e004';

    public static function issued(
        string $id = self::INVOICE,
        string $status = 'ISSUED',
        int $total = 8870,
        int $due = 8870,
        ?string $client = self::CLIENT,
        ?string $admission = self::ADMISSION,
        ?string $number = 'FAC-2026-000814',
        ?string $issuedAt = '2026-10-16 08:30:00',
    ): InvoiceDetails {
        return new InvoiceDetails(
            id: $id,
            clinicId: self::CLINIC,
            number: $number,
            status: $status,
            statusLabel: $status,
            admissionId: $admission,
            payerClientId: $client,
            payerName: 'Mme Sophie Dubois',
            payerAddressLines: [],
            lines: [],
            payments: [],
            taxBreakdown: [
                ['ratePercent' => '20.00', 'netMinorUnits' => 7392, 'taxMinorUnits' => 1478, 'grossMinorUnits' => 8870, 'allowanceMinorUnits' => 0],
                ['ratePercent' => '0.00', 'netMinorUnits' => 0, 'taxMinorUnits' => 0, 'grossMinorUnits' => 0, 'allowanceMinorUnits' => 0],
            ],
            currency: 'EUR',
            totalNetBeforeDiscountMinorUnits: 7392,
            discountMinorUnits: 0,
            discountKind: null,
            discountValue: null,
            discountReason: null,
            totalNetMinorUnits: 7392,
            totalTaxMinorUnits: 1478,
            totalGrossMinorUnits: $total,
            amountPaidMinorUnits: $total - $due,
            balanceDueMinorUnits: $due,
            notes: null,
            createdAt: new \DateTimeImmutable('2026-10-15 22:30:00', new \DateTimeZone('UTC')),
            issuedAt: null !== $issuedAt ? new \DateTimeImmutable($issuedAt, new \DateTimeZone('UTC')) : null,
            paidAt: null,
            cancelledAt: null,
            cancellationReason: null,
        );
    }
}
