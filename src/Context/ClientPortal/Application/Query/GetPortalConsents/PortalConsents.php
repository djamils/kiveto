<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\GetPortalConsents;

final readonly class PortalConsents
{
    /**
     * @param list<PortalConsent> $consents every kind, in screen order
     */
    public function __construct(
        public ?string $preferredChannel,
        public array $consents,
    ) {
    }
}
