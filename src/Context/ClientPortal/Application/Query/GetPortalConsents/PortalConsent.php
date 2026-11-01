<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\GetPortalConsents;

/** One consent kind as the owner sees it; no channel = not consented. */
final readonly class PortalConsent
{
    /**
     * @param list<string>        $channels  `sms` and / or `email`
     * @param ?\DateTimeImmutable $grantedAt the latest date among the files holding it, null without channel
     * @param string              $effect    `send_control` or `channel_preference`
     */
    public function __construct(
        public string $kind,
        public array $channels,
        public ?\DateTimeImmutable $grantedAt,
        public string $effect,
    ) {
    }
}
