<?php

declare(strict_types=1);

namespace App\System\Audit\Application\Query\ExportAuditLog;

use App\Shared\Domain\Time\ClockInterface;
use App\System\Audit\Application\Port\AuditLogCriteria;
use App\System\Audit\Application\Port\AuditLogReadRepositoryInterface;
use App\System\Audit\Application\Query\AuditEntryView;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * At most the 10 000 newest entries: a wider export is narrowed with the
 * period or the filters.
 */
#[AsMessageHandler]
final readonly class ExportAuditLogHandler
{
    public const int MAX_ROWS = 10_000;

    public function __construct(
        private AuditLogReadRepositoryInterface $log,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return list<AuditEntryView>
     */
    public function __invoke(ExportAuditLog $query): array
    {
        return $this->log->search(
            AuditLogCriteria::fromFilters(
                $query->category,
                $query->clinicId,
                $query->actorKind,
                $query->userId,
                $query->periodDays,
                $query->text,
                $this->clock->now(),
            ),
            0,
            self::MAX_ROWS,
        );
    }
}
