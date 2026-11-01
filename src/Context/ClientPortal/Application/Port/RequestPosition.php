<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

/** Where a page of requests stopped: the last request's submit instant (UTC) and id. */
final readonly class RequestPosition
{
    public function __construct(
        public \DateTimeImmutable $submittedAt,
        public string $id,
    ) {
    }
}
