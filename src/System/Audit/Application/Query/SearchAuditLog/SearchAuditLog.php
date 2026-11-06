<?php

declare(strict_types=1);

namespace App\System\Audit\Application\Query\SearchAuditLog;

use App\Shared\Application\Bus\QueryInterface;
use App\System\Audit\Domain\ValueObject\ActorKind;
use App\System\Audit\Domain\ValueObject\AuditCategory;

final readonly class SearchAuditLog implements QueryInterface
{
    public function __construct(
        public int $page = 1,
        public int $perPage = 12,
        public ?AuditCategory $category = null,
        public ?string $clinicId = null,
        public ?ActorKind $actorKind = null,
        public ?string $userId = null,
        public ?int $periodDays = null,
        public ?string $text = null,
    ) {
    }
}
