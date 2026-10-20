<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\ElectronicInvoicing\Support;

use App\System\ElectronicInvoicing\Application\Command\RequestInvoiceTransmission\TransmittableAddress;
use App\System\ElectronicInvoicing\Application\Command\RequestInvoiceTransmission\TransmittableAllowance;
use App\System\ElectronicInvoicing\Application\Command\RequestInvoiceTransmission\TransmittableElectronicAddress;
use App\System\ElectronicInvoicing\Application\Command\RequestInvoiceTransmission\TransmittableInvoice;
use App\System\ElectronicInvoicing\Application\Command\RequestInvoiceTransmission\TransmittableLine;
use App\System\ElectronicInvoicing\Application\Command\RequestInvoiceTransmission\TransmittableNote;
use App\System\ElectronicInvoicing\Application\Command\RequestInvoiceTransmission\TransmittableParty;
use App\System\ElectronicInvoicing\Application\Command\RequestInvoiceTransmission\VatBreakdown;

/**
 * Neutral documents for the ElectronicInvoicing tests.
 */
final class TransmittableInvoices
{
    public const string SELLER = '812456903';
    public const string BUYER  = '523456788';

    public static function invoice(
        string $documentId = 'doc-1',
        string $number = 'PAR-2026-000001',
        string $buyerSiren = self::BUYER,
        string $sellerSiren = self::SELLER,
    ): TransmittableInvoice {
        return new TransmittableInvoice(
            documentId: $documentId,
            kind: TransmittableInvoice::KIND_INVOICE,
            number: $number,
            issueDate: new \DateTimeImmutable('2026-10-21'),
            typeCode: '380',
            invoicingFramework: 'S1',
            processingRule: 'B2B',
            currency: 'EUR',
            seller: self::party('SELARL Clinique du Parc', $sellerSiren, 'FR20812456903', ['18 rue des Abbesses'], '75018', 'Paris'),
            buyer: self::party('EARL Élevage des Calanques', $buyerSiren, null, ['Chemin des Goudes'], '13008', 'Marseille'),
            buyerContact: 'Mme Claire Rostand',
            deliveryDate: new \DateTimeImmutable('2026-10-21'),
            dueDate: new \DateTimeImmutable('2026-11-20'),
            paymentTermsNote: 'Paiement à 30 jours',
            lineTotal: 5000,
            allowanceTotal: 500,
            taxBasis: 4500,
            vatTotal: 900,
            grossTotal: 5400,
            paidAmount: 0,
            duePayable: 5400,
            lines: [new TransmittableLine('1', 'Consultation', '1', 'C62', '50.000000', 5000, 'S', '20.00')],
            allowances: [new TransmittableAllowance(500, 'S', '20.00', 'Remise')],
            vatBreakdown: [new VatBreakdown('S', '20.00', 4500, 900)],
            notes: [new TransmittableNote('PMT', 'Indemnité forfaitaire pour frais de recouvrement en cas de retard de paiement : 40 €.')],
        );
    }

    public static function creditNote(string $documentId = 'cn-1', string $number = 'AVPAR-2026-000001', string $invoiceNumber = 'PAR-2026-000001'): TransmittableInvoice
    {
        $invoice = self::invoice($documentId, $number);

        return new TransmittableInvoice(
            documentId: $documentId,
            kind: TransmittableInvoice::KIND_CREDIT_NOTE,
            number: $number,
            issueDate: $invoice->issueDate,
            typeCode: '381',
            invoicingFramework: 'S1',
            processingRule: 'B2B',
            currency: 'EUR',
            seller: $invoice->seller,
            buyer: $invoice->buyer,
            buyerContact: null,
            deliveryDate: null,
            dueDate: null,
            paymentTermsNote: null,
            lineTotal: 5000,
            allowanceTotal: 0,
            taxBasis: 5000,
            vatTotal: 1000,
            grossTotal: 6000,
            paidAmount: 0,
            duePayable: 6000,
            lines: [new TransmittableLine('1', 'Consultation', '1', 'C62', '50.000000', 5000, 'S', '20.00')],
            allowances: [],
            vatBreakdown: [new VatBreakdown('S', '20.00', 5000, 1000)],
            notes: [],
            precedingInvoiceNumber: $invoiceNumber,
            precedingInvoiceDate: new \DateTimeImmutable('2026-10-21'),
        );
    }

    /**
     * @param list<string> $lines
     */
    private static function party(string $name, string $siren, ?string $vat, array $lines, string $postalCode, string $city): TransmittableParty
    {
        return new TransmittableParty(
            $name,
            $siren,
            $vat,
            new TransmittableAddress($lines, $postalCode, $city, 'FR'),
            new TransmittableElectronicAddress('0225', $siren),
        );
    }
}
