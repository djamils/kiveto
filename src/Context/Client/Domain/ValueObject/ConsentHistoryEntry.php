<?php

declare(strict_types=1);

namespace App\Context\Client\Domain\ValueObject;

/**
 * A consent change as the history keeps it: what changed, when, where and
 * by whom, and the version of the wording the owner agreed to when known.
 * An approximate entry takes over a consent recorded before the history:
 * its date is only an upper bound.
 */
final readonly class ConsentHistoryEntry
{
    public function __construct(
        public ClinicId $clinicId,
        public ClientId $clientId,
        public ConsentChange $change,
        public \DateTimeImmutable $at,
        public ConsentSource $source,
        public ConsentActor $actor,
        public ?string $wordingVersion = null,
        public bool $approximate = false,
    ) {
    }
}
