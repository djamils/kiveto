<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\GetLatestRenewalStatuses;

use App\Shared\Application\Bus\QueryInterface;

/**
 * `renewalStatus` of each listed prescription (S6c): its latest renewal
 * request. Answers `array<string, RenewalStatus>` keyed by prescription
 * id; a prescription without request is left out.
 */
final readonly class GetLatestRenewalStatuses implements QueryInterface
{
    /**
     * @param list<string> $prescriptionIds
     */
    public function __construct(
        public string $clinicId,
        public array $prescriptionIds,
    ) {
    }
}
