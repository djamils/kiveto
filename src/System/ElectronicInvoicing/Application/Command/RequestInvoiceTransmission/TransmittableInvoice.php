<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Application\Command\RequestInvoiceTransmission;

/**
 * A neutral fiscal document to transmit: an invoice (380) or a credit note
 * (381), with nothing specific to the business that issued it.
 *
 * Amounts are integer minor units, VAT rates decimal strings, unit net
 * prices six-decimal strings. The document id is the tracking id the
 * transmission is known by.
 */
final readonly class TransmittableInvoice
{
    public const string KIND_INVOICE     = 'invoice';
    public const string KIND_CREDIT_NOTE = 'credit_note';

    /**
     * @param list<TransmittableLine>      $lines
     * @param list<TransmittableAllowance> $allowances
     * @param list<VatBreakdown>           $vatBreakdown
     * @param list<TransmittableNote>      $notes
     */
    public function __construct(
        public string $documentId,
        public string $kind,
        public string $number,
        public \DateTimeImmutable $issueDate,
        public string $typeCode,
        public string $invoicingFramework,
        public string $processingRule,
        public string $currency,
        public TransmittableParty $seller,
        public TransmittableParty $buyer,
        public ?string $buyerContact,
        public ?\DateTimeImmutable $deliveryDate,
        public ?\DateTimeImmutable $dueDate,
        public ?string $paymentTermsNote,
        public int $lineTotal,
        public int $allowanceTotal,
        public int $taxBasis,
        public int $vatTotal,
        public int $grossTotal,
        public int $paidAmount,
        public int $duePayable,
        public array $lines,
        public array $allowances,
        public array $vatBreakdown,
        public array $notes,
        public ?string $precedingInvoiceNumber = null,
        public ?\DateTimeImmutable $precedingInvoiceDate = null,
    ) {
    }

    public function isCreditNote(): bool
    {
        return self::KIND_CREDIT_NOTE === $this->kind;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $date = static fn (?\DateTimeImmutable $d): ?string => $d?->format('Y-m-d');

        return [
            'documentId'             => $this->documentId,
            'kind'                   => $this->kind,
            'number'                 => $this->number,
            'issueDate'              => $this->issueDate->format('Y-m-d'),
            'typeCode'               => $this->typeCode,
            'invoicingFramework'     => $this->invoicingFramework,
            'processingRule'         => $this->processingRule,
            'currency'               => $this->currency,
            'seller'                 => $this->seller->toArray(),
            'buyer'                  => $this->buyer->toArray(),
            'buyerContact'           => $this->buyerContact,
            'deliveryDate'           => $date($this->deliveryDate),
            'dueDate'                => $date($this->dueDate),
            'paymentTermsNote'       => $this->paymentTermsNote,
            'lineTotal'              => $this->lineTotal,
            'allowanceTotal'         => $this->allowanceTotal,
            'taxBasis'               => $this->taxBasis,
            'vatTotal'               => $this->vatTotal,
            'grossTotal'             => $this->grossTotal,
            'paidAmount'             => $this->paidAmount,
            'duePayable'             => $this->duePayable,
            'lines'                  => array_map(static fn (TransmittableLine $l): array => $l->toArray(), $this->lines),
            'allowances'             => array_map(static fn (TransmittableAllowance $a): array => $a->toArray(), $this->allowances),
            'vatBreakdown'           => array_map(static fn (VatBreakdown $v): array => $v->toArray(), $this->vatBreakdown),
            'notes'                  => array_map(static fn (TransmittableNote $n): array => $n->toArray(), $this->notes),
            'precedingInvoiceNumber' => $this->precedingInvoiceNumber,
            'precedingInvoiceDate'   => $date($this->precedingInvoiceDate),
        ];
    }

    /**
     * @param array<string, mixed> $data what toArray() produced
     */
    public static function fromArray(array $data): self
    {
        /** @var array{documentId: string, kind: string, number: string, issueDate: string, typeCode: string, invoicingFramework: string, processingRule: string, currency: string, seller: array{name: string, siren: string, vatNumber: ?string, address: array{lines: list<string>, postalCode: ?string, city: ?string, countryCode: string}, electronicAddress: array{scheme: string, identifier: string}}, buyer: array{name: string, siren: string, vatNumber: ?string, address: array{lines: list<string>, postalCode: ?string, city: ?string, countryCode: string}, electronicAddress: array{scheme: string, identifier: string}}, buyerContact: ?string, deliveryDate: ?string, dueDate: ?string, paymentTermsNote: ?string, lineTotal: int, allowanceTotal: int, taxBasis: int, vatTotal: int, grossTotal: int, paidAmount: int, duePayable: int, lines: list<array{lineId: string, name: string, quantity: string, unitCode: string, unitNet: string, net: int, vatCategory: string, vatRate: string}>, allowances: list<array{amount: int, vatCategory: string, vatRate: string, reason: string}>, vatBreakdown: list<array{vatCategory: string, vatRate: string, base: int, vat: int}>, notes: list<array{subjectCode: string, text: string}>, precedingInvoiceNumber: ?string, precedingInvoiceDate: ?string} $data */
        $date = static fn (?string $d): ?\DateTimeImmutable => null === $d ? null : new \DateTimeImmutable($d);

        return new self(
            documentId: $data['documentId'],
            kind: $data['kind'],
            number: $data['number'],
            issueDate: new \DateTimeImmutable($data['issueDate']),
            typeCode: $data['typeCode'],
            invoicingFramework: $data['invoicingFramework'],
            processingRule: $data['processingRule'],
            currency: $data['currency'],
            seller: TransmittableParty::fromArray($data['seller']),
            buyer: TransmittableParty::fromArray($data['buyer']),
            buyerContact: $data['buyerContact'],
            deliveryDate: $date($data['deliveryDate']),
            dueDate: $date($data['dueDate']),
            paymentTermsNote: $data['paymentTermsNote'],
            lineTotal: $data['lineTotal'],
            allowanceTotal: $data['allowanceTotal'],
            taxBasis: $data['taxBasis'],
            vatTotal: $data['vatTotal'],
            grossTotal: $data['grossTotal'],
            paidAmount: $data['paidAmount'],
            duePayable: $data['duePayable'],
            lines: array_map(TransmittableLine::fromArray(...), $data['lines']),
            allowances: array_map(TransmittableAllowance::fromArray(...), $data['allowances']),
            vatBreakdown: array_map(VatBreakdown::fromArray(...), $data['vatBreakdown']),
            notes: array_map(TransmittableNote::fromArray(...), $data['notes']),
            precedingInvoiceNumber: $data['precedingInvoiceNumber'],
            precedingInvoiceDate: $date($data['precedingInvoiceDate']),
        );
    }
}
