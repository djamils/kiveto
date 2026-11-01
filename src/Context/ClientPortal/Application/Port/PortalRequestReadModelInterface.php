<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

/**
 * The pet change and renewal requests as lists, newest first
 * (`submitted_at DESC, id DESC`), after the given position.
 */
interface PortalRequestReadModelInterface
{
    /**
     * The requests an owner account sent in a clinic, scoped in SQL to the
     * client files it reaches through a confirmed link there.
     *
     * @return list<PortalRequestRecord>
     */
    public function ownerPage(string $userId, string $clinicId, ?RequestPosition $after, int $limit): array;

    /**
     * The clinic's requests in the given statuses, of one kind or both.
     *
     * @param non-empty-list<string> $statuses
     * @param ?string                $kind     pet_change|prescription_renewal, null for both
     *
     * @return list<PortalRequestRecord>
     */
    public function clinicPage(string $clinicId, array $statuses, ?string $kind, ?RequestPosition $after, int $limit): array;
}
