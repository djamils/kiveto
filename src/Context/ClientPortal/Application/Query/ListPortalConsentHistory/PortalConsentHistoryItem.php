<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\ListPortalConsentHistory;

/**
 * One change as the owner sees it: a channel of a kind granted or withdrawn
 * at an instant, on one or several of their files.
 */
final readonly class PortalConsentHistoryItem
{
    /**
     * @param string $action      `granted` or `withdrawn`
     * @param string $source      `portal`, `clinic` or `system`
     * @param string $author      « La clinique » for a change made at the clinic
     * @param bool   $approximate a consent taken over from before the history: `at` is an upper bound
     */
    public function __construct(
        public string $kind,
        public string $channel,
        public string $action,
        public \DateTimeImmutable $at,
        public string $source,
        public string $author,
        public bool $approximate,
    ) {
    }
}
