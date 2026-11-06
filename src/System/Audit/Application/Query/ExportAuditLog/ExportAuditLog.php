<?php

declare(strict_types=1);

namespace App\System\Audit\Application\Query\ExportAuditLog;

use App\Shared\Application\Bus\QueryInterface;
use App\System\Audit\Domain\ValueObject\ActorKind;
use App\System\Audit\Domain\ValueObject\AuditCategory;

/**
 * The entries of the current filters, for the CSV export.
 */
final readonly class ExportAuditLog implements QueryInterface
{
    public function __construct(
        public ?AuditCategory $category = null,
        public ?string $clinicId = null,
        public ?ActorKind $actorKind = null,
        public ?string $userId = null,
        public ?int $periodDays = null,
        public ?string $text = null,
    ) {
    }
}
