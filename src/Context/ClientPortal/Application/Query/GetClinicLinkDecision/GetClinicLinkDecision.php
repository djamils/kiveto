<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\GetClinicLinkDecision;

use App\Shared\Application\Bus\QueryInterface;

/**
 * One answered « Rattachement » of the clinic, by its decision id. Answers
 * ?ClinicLinkRequest: null for an unknown id or another clinic's.
 */
final readonly class GetClinicLinkDecision implements QueryInterface
{
    public function __construct(
        public string $clinicId,
        public string $decisionId,
    ) {
    }
}
