<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\ListLinkSuggestions;

final readonly class LinkSuggestion
{
    /**
     * @param string $id        opaque (HMAC of the account and the clinic)
     * @param string $matchedOn always `phone`
     */
    public function __construct(
        public string $id,
        public string $clinicName,
        public ?string $city,
        public string $matchedOn = 'phone',
    ) {
    }
}
