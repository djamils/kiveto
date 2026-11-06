<?php

declare(strict_types=1);

namespace App\System\Audit\Application\Port;

use App\System\Audit\Application\Query\AuditEntryView;

/**
 * Newest first everywhere (occurred at, then id: UUIDv7 ids keep the order
 * of one second).
 */
interface AuditLogReadRepositoryInterface
{
    /**
     * @return list<AuditEntryView>
     */
    public function search(AuditLogCriteria $criteria, int $offset, int $limit): array;

    public function count(AuditLogCriteria $criteria): int;

    /**
     * The entries about one clinic.
     *
     * @return list<AuditEntryView>
     */
    public function forClinic(string $clinicId, int $limit): array;

    /**
     * The entries an account wrote or that target it.
     *
     * @return list<AuditEntryView>
     */
    public function forUser(string $userId, int $limit): array;
}
