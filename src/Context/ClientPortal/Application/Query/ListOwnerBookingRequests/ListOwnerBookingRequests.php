<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\ListOwnerBookingRequests;

use App\Shared\Application\Bus\QueryInterface;

/**
 * `GET /me/booking-requests`: the account's new-client booking requests,
 * every clinic, newest first. Answers CursorPage<OwnerBookingRequest>;
 * `InvalidCursorException`.
 */
final readonly class ListOwnerBookingRequests implements QueryInterface
{
    public function __construct(
        public string $userId,
        public ?string $cursor = null,
        public ?int $limit = null,
    ) {
    }
}
