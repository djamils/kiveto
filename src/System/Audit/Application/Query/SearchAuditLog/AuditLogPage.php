<?php

declare(strict_types=1);

namespace App\System\Audit\Application\Query\SearchAuditLog;

use App\System\Audit\Application\Query\AuditEntryView;

final readonly class AuditLogPage
{
    /**
     * @param list<AuditEntryView> $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $perPage,
    ) {
    }

    public function pageCount(): int
    {
        return max(1, (int) ceil($this->total / $this->perPage));
    }
}
