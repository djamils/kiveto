<?php

declare(strict_types=1);

namespace App\Context\Billing\Domain\Event;

use App\Shared\Domain\Event\AbstractIntegrationEvent;

/**
 * An invoice was issued — every one, private or professional, with or without a
 * client payer. Thin payload: the Client Portal reads what it needs back
 * through Billing's queries and decides itself whether to publish it.
 */
final readonly class InvoiceIssuedForPortalIntegrationEvent extends AbstractIntegrationEvent
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
