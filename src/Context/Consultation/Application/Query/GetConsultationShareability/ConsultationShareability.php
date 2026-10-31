<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Query\GetConsultationShareability;

/**
 * The share check of a consultation report: shareable once the consultation
 * is closed and its patient is an animal with an active primary owner, who
 * is the client the report goes to.
 */
final readonly class ConsultationShareability
{
    public const string NOT_CLOSED       = 'not_closed';
    public const string NO_ANIMAL        = 'no_animal';
    public const string NO_PRIMARY_OWNER = 'no_primary_owner';

    /**
     * @param string|null $reason           one of the constants above, null when shareable
     * @param string|null $practitionerName « Dr Claire Lambert », null when not resolvable (snapshot at share time)
     */
    public function __construct(
        public bool $shareable,
        public ?string $reason,
        public string $patientId,
        public ?string $animalId,
        public ?string $primaryOwnerClientId,
        public \DateTimeImmutable $startedAtUtc,
        public ?\DateTimeImmutable $closedAtUtc,
        public ?string $practitionerName,
    ) {
    }
}
