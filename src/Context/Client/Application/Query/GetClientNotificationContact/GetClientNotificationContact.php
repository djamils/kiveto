<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Query\GetClientNotificationContact;

use App\Shared\Application\Bus\QueryInterface;

/**
 * How to reach one client of the clinic by an automated message; null when
 * the client does not belong to the clinic.
 */
final readonly class GetClientNotificationContact implements QueryInterface
{
    public function __construct(
        public string $clinicId,
        public string $clientId,
    ) {
    }
}
