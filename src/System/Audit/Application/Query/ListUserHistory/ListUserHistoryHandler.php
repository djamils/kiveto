<?php

declare(strict_types=1);

namespace App\System\Audit\Application\Query\ListUserHistory;

use App\System\Audit\Application\Port\AuditLogReadRepositoryInterface;
use App\System\Audit\Application\Query\AuditEntryView;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListUserHistoryHandler
{
    public function __construct(
        private AuditLogReadRepositoryInterface $log,
    ) {
    }

    /**
     * @return list<AuditEntryView>
     */
    public function __invoke(ListUserHistory $query): array
    {
        return $this->log->forUser($query->userId, max(1, $query->limit));
    }
}
