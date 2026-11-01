<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Service;

use App\Context\ClientPortal\Application\Port\MobileFileMatch;

/** One clinic the account may ask to be linked to, with its files not linked yet. */
final readonly class LinkSuggestionCandidate
{
    /**
     * @param list<MobileFileMatch> $files the matching files the account has no link to (any status)
     */
    public function __construct(
        public string $id,
        public string $clinicId,
        public string $clinicName,
        public ?string $city,
        public array $files,
    ) {
    }
}
