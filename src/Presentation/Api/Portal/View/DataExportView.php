<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\View;

use App\Context\ClientPortal\Application\Query\ListDataExports\DataExportSummary;
use App\Presentation\Api\Portal\Support\ApiDate;

/** A data export of the account; instants in UTC. */
final readonly class DataExportView
{
    /**
     * @param string $status pending|ready|failed|expired
     */
    public function __construct(
        public string $id,
        public string $status,
        public string $requestedAt,
        public ?string $readyAt,
        public ?string $expiresAt,
    ) {
    }

    public static function of(DataExportSummary $export): self
    {
        return new self($export->id, $export->status, ApiDate::format($export->requestedAt), ApiDate::formatNullable($export->readyAt), ApiDate::formatNullable($export->expiresAt));
    }
}
