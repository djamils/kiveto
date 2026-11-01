<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Service;

use App\Context\ClientPortal\Application\Port\ClientConsentRecord;
use App\Context\ClientPortal\Application\Port\ClientConsentsInterface;
use App\Context\ClientPortal\Domain\Exception\ClinicNotLinkedException;
use App\Context\ClientPortal\Domain\Link\ClientLinkRepositoryInterface;

/**
 * The client files whose consents the owner account reads and writes in a
 * clinic: the confirmed-linked files that are not archived (an archived
 * file keeps its consents untouched).
 */
final readonly class PortalConsentFiles
{
    public function __construct(
        private ClientLinkRepositoryInterface $links,
        private ClientConsentsInterface $consents,
    ) {
    }

    /**
     * In link order, oldest link first.
     *
     * @throws ClinicNotLinkedException when no such file exists
     *
     * @return non-empty-list<ClientConsentRecord>
     */
    public function activeOf(string $userId, string $clinicId): array
    {
        $clientIds = [];

        foreach ($this->links->findConfirmedByUser($userId) as $link) {
            if ($link->clinicId() === $clinicId) {
                $clientIds[] = $link->clientId();
            }
        }

        $clientIds = array_values(array_unique($clientIds));
        $records   = [] === $clientIds ? [] : $this->consents->consentsOf($clinicId, $clientIds);
        $active    = [];

        foreach ($clientIds as $clientId) {
            $record = $records[$clientId] ?? null;

            if (null !== $record && !$record->archived) {
                $active[] = $record;
            }
        }

        if ([] === $active) {
            throw new ClinicNotLinkedException($clinicId);
        }

        return $active;
    }
}
