<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\GetClinicLinkRequest;

use App\Shared\Application\Bus\QueryInterface;

/**
 * The « Rattachement » request of one account in the clinic. Answers
 * ?ClinicLinkRequest: null when nothing is pending (confirmed, rejected,
 * never asked, another clinic's).
 */
final readonly class GetClinicLinkRequest implements QueryInterface
{
    public function __construct(
        public string $clinicId,
        public string $userId,
    ) {
    }
}
