<?php

declare(strict_types=1);

namespace App\Context\Billing\Domain\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

final readonly class InvoicePaid extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'billing';
    protected const int    VERSION         = 1;

    public function __construct(
        public string $invoiceId,
        public string $clinicId,
        public ?string $admissionId,
        public int $totalGrossMinorUnits,
        public string $currency,
        public string $paidAt,
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
            'admissionId'          => $this->admissionId,
            'totalGrossMinorUnits' => $this->totalGrossMinorUnits,
            'currency'             => $this->currency,
            'paidAt'               => $this->paidAt,
        ];
    }
}
