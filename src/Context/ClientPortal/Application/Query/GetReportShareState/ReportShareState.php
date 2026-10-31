<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\GetReportShareState;

final readonly class ReportShareState
{
    public const string UNKNOWN          = 'unknown';
    public const string NOT_CLOSED       = 'not_closed';
    public const string NO_ANIMAL        = 'no_animal';
    public const string NO_PRIMARY_OWNER = 'no_primary_owner';

    /**
     * @param ?string             $reason   one of the constants when not shareable, else null
     * @param ?\DateTimeImmutable $sharedAt the last share, null when never shared (or withdrawn)
     */
    public function __construct(
        public bool $shareable,
        public ?string $reason,
        public ?\DateTimeImmutable $sharedAt,
    ) {
    }
}
