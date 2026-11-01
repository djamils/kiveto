<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\ListDataExports;

final readonly class DataExportSummary
{
    /**
     * @param string $status pending|ready|failed|expired, as of now
     */
    public function __construct(
        public string $id,
        public string $status,
        public \DateTimeImmutable $requestedAt,
        public ?\DateTimeImmutable $readyAt,
        public ?\DateTimeImmutable $expiresAt,
    ) {
    }
}
