<?php

declare(strict_types=1);

namespace App\System\Audit\Application\Query\ListClinicActivity;

use App\System\Audit\Application\Port\AuditLogReadRepositoryInterface;
use App\System\Audit\Application\Query\AuditEntryView;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListClinicActivityHandler
{
    public function __construct(
        private AuditLogReadRepositoryInterface $log,
    ) {
    }

    /**
     * @return list<AuditEntryView>
     */
    public function __invoke(ListClinicActivity $query): array
    {
        return $this->log->forClinic($query->clinicId, max(1, $query->limit));
    }
}
