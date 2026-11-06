<?php

declare(strict_types=1);

namespace App\System\Audit\Application\Query\SearchAuditLog;

use App\Shared\Domain\Time\ClockInterface;
use App\System\Audit\Application\Port\AuditLogCriteria;
use App\System\Audit\Application\Port\AuditLogReadRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * A page of the audit log. A page beyond the last answers the last one.
 */
#[AsMessageHandler]
final readonly class SearchAuditLogHandler
{
    public function __construct(
        private AuditLogReadRepositoryInterface $log,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(SearchAuditLog $query): AuditLogPage
    {
        $perPage  = max(1, $query->perPage);
        $criteria = AuditLogCriteria::fromFilters(
            $query->category,
            $query->clinicId,
            $query->actorKind,
            $query->userId,
            $query->periodDays,
            $query->text,
            $this->clock->now(),
        );

        $total    = $this->log->count($criteria);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page     = min(max(1, $query->page), $lastPage);

        return new AuditLogPage(
            items: 0 === $total ? [] : $this->log->search($criteria, ($page - 1) * $perPage, $perPage),
            total: $total,
            page: $page,
            perPage: $perPage,
        );
    }
}
