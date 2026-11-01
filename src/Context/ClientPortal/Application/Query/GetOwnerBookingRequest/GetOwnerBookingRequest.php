<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\GetOwnerBookingRequest;

use App\Shared\Application\Bus\QueryInterface;

/** One booking request of the account → ?OwnerBookingRequest (the 201 of the booking route). */
final readonly class GetOwnerBookingRequest implements QueryInterface
{
    public function __construct(
        public string $userId,
        public string $requestId,
    ) {
    }
}
