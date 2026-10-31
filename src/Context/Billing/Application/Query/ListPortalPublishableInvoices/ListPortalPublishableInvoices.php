<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Query\ListPortalPublishableInvoices;

use App\Shared\Application\Bus\QueryInterface;

/**
 * One page, across all clinics, of the invoices the Client Portal's go-live
 * import publishes: issued (then possibly paid or credited, never cancelled)
 * since a date, with a client as payer. Ordered by id, keyset after an id.
 */
final readonly class ListPortalPublishableInvoices implements QueryInterface
{
    public function __construct(
        public \DateTimeImmutable $issuedFrom,
        public ?string $afterId,
        public int $limit,
    ) {
    }
}
