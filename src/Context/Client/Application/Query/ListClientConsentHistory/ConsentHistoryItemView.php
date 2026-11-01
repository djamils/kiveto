<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Query\ListClientConsentHistory;

/**
 * One channel of one consent granted or withdrawn. `approximate` marks the
 * rows taken over from the consents on file before the history existed:
 * their date is the consent's date, an upper bound.
 */
final readonly class ConsentHistoryItemView
{
    /**
     * @param string $kind      ConsentKind value
     * @param string $channel   `sms` or `email`
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
        public ?string $actorId,
        public string $authorLabel,
        public ?string $wordingVersion,
        public bool $approximate,
    ) {
    }
}
