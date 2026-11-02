<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\ListClinicLinkRequests;

use App\Shared\Application\Bus\QueryInterface;

/**
 * VetApp « Demandes Portal », type « Rattachement »: the accounts waiting
 * for the clinic to confirm their pending links, one request per account,
 * oldest first — or, with `decided`, the clinic's answers, one per decision,
 * the latest first (the most recent 500). Answers list<ClinicLinkRequest>.
 */
final readonly class ListClinicLinkRequests implements QueryInterface
{
    public function __construct(
        public string $clinicId,
        public bool $decided = false,
    ) {
    }
}
