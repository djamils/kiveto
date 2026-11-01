<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\GetPortalRequest;

use App\Shared\Application\Bus\QueryInterface;

/**
 * VetApp detail of a pet change or renewal request of the clinic. Answers
 * `?PortalRequestDetails` (null: unknown in the clinic, or a questionnaire).
 */
final readonly class GetPortalRequest implements QueryInterface
{
    public function __construct(
        public string $clinicId,
        public string $requestId,
    ) {
    }
}
