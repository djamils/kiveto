<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Query\ListClinicsOpenToNewClients;

use App\Shared\Application\Bus\QueryInterface;

/**
 * Which of these clinics take online booking requests from new clients →
 * `list<string>` (the subset, in the given order): settings `enabled` and
 * `newClientBookingEnabled`, and at least one motif not archived, offered
 * online and open to new clients. One call for a page of the clinic
 * directory (S7c).
 */
final readonly class ListClinicsOpenToNewClients implements QueryInterface
{
    /**
     * @param list<string> $clinicIds
     */
    public function __construct(public array $clinicIds)
    {
    }
}
