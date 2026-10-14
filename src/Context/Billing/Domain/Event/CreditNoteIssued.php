<?php

declare(strict_types=1);

namespace App\Context\Billing\Domain\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

final readonly class CreditNoteIssued extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'billing';
    protected const int    VERSION         = 1;

    public function __construct(
        public string $creditNoteId,
        public string $clinicId,
        public string $invoiceId,
        public string $number,
        public int $totalGrossMinorUnits,
        public int $refundedMinorUnits,
        public string $currency,
        public string $issuedAt,
    ) {
    }

    public function aggregateId(): string
    {
        return $this->creditNoteId;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'creditNoteId'         => $this->creditNoteId,
            'clinicId'             => $this->clinicId,
            'invoiceId'            => $this->invoiceId,
            'number'               => $this->number,
            'totalGrossMinorUnits' => $this->totalGrossMinorUnits,
            'refundedMinorUnits'   => $this->refundedMinorUnits,
            'currency'             => $this->currency,
            'issuedAt'             => $this->issuedAt,
        ];
    }
}
