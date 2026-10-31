<?php

declare(strict_types=1);

namespace App\Context\Billing\Domain\Event;

use App\Shared\Domain\Event\AbstractIntegrationEvent;

/**
 * An issued invoice was cancelled: the Client Portal withdraws it. Thin
 * payload, like its issued counterpart.
 */
final readonly class InvoiceCancelledIntegrationEvent extends AbstractIntegrationEvent
{
    protected const string BOUNDED_CONTEXT = 'billing';
    protected const int    VERSION         = 1;

    public function __construct(
        public string $invoiceId,
        public string $clinicId,
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
            'invoiceId' => $this->invoiceId,
            'clinicId'  => $this->clinicId,
        ];
    }
}
