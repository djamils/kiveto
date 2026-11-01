<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

/**
 * One row of the consent history of a client file: one channel of one kind
 * granted or withdrawn. `at` is UTC, to the second.
 */
final readonly class ClientConsentChange
{
    /**
     * @param string $action    `granted` or `withdrawn`
     * @param string $source    `portal`, `clinic` or `system`
     * @param string $actorType `portal_user`, `staff` or `system`
     */
    public function __construct(
        public string $id,
        public string $clientId,
        public string $kind,
        public string $channel,
        public string $action,
        public \DateTimeImmutable $at,
        public string $source,
        public string $actorType,
        public string $authorLabel,
        public bool $approximate,
    ) {
    }
}
