<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Query\ListInvoiceDispatches;

use App\Context\Billing\Application\Port\DeliveryStatus;

final readonly class InvoiceDispatchView
{
    public const string SEND     = 'SEND';
    public const string REMINDER = 'REMINDER';

    /**
     * @param string               $kind       SEND or REMINDER
     * @param list<string>         $channels   EMAIL and / or SMS, as asked
     * @param list<DeliveryStatus> $deliveries one per message requested; empty while the send is queued
     */
    public function __construct(
        public string $kind,
        public \DateTimeImmutable $at,
        public ?string $authorUserId,
        public array $channels,
        public array $deliveries,
    ) {
    }
}
