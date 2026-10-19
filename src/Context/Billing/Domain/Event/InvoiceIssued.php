<?php

declare(strict_types=1);

namespace App\Context\Billing\Domain\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

final readonly class InvoiceIssued extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'billing';
    protected const int    VERSION         = 1;

    public function __construct(
        public string $invoiceId,
        public string $clinicId,
        public string $number,
        public int $totalGrossMinorUnits,
        public string $currency,
        public string $issuedAt,
        // B2B or B2C: lets a listener tell a professional invoice apart
        // without reloading the aggregate.
        public string $processingRule = 'B2C',
    ) {
    }

    public function aggregateId(): string
    {
        return $this->invoiceId;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'invoiceId'            => $this->invoiceId,
            'clinicId'             => $this->clinicId,
            'number'               => $this->number,
            'totalGrossMinorUnits' => $this->totalGrossMinorUnits,
            'currency'             => $this->currency,
            'issuedAt'             => $this->issuedAt,
            'processingRule'       => $this->processingRule,
        ];
    }
}
