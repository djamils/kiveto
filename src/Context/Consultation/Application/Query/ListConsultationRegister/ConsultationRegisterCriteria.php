<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Query\ListConsultationRegister;

/**
 * Validated shape the read repository lists the register from.
 *
 * Everything another context knows has already been turned into identifiers
 * or codes by the handler: the patients behind the animals of the search, the
 * visits that are over, the emergency visits, the catalogue codes of the
 * surgical and laboratory acts.
 */
final readonly class ConsultationRegisterCriteria
{
    public const string STAGE_IN_PROGRESS = 'IN_PROGRESS';
    public const string STAGE_TO_CLOSE    = 'TO_CLOSE';
    public const string STAGE_CLOSED      = 'CLOSED';

    public const string KIND_URGENT  = 'URGENT';
    public const string KIND_SURGERY = 'SURGERY';
    public const string KIND_LAB     = 'LAB';

    /**
     * @param list<string>  $textMatchPatientIds
     * @param list<string>  $departedAdmissionIds   visits already over while a consultation is still open
     * @param ?list<string> $restrictToAdmissionIds
     * @param ?list<string> $restrictToActCodes     consultations billing one of these acts, null for all
     * @param ?list<string> $consultationIds
     */
    public function __construct(
        public \DateTimeImmutable $dayStartUtc,
        public ?string $searchTerm = null,
        public array $textMatchPatientIds = [],
        public ?\DateTimeImmutable $startedAfterUtc = null,
        public ?string $stage = null,
        public array $departedAdmissionIds = [],
        public ?string $practitionerUserId = null,
        public ?array $restrictToAdmissionIds = null,
        public ?array $restrictToActCodes = null,
        public ?array $consultationIds = null,
        public int $page = 1,
        public int $limit = 25,
    ) {
        if ($page < 1) {
            throw new \InvalidArgumentException('Page must be >= 1.');
        }

        if ($limit < 1 || $limit > 100) {
            throw new \InvalidArgumentException('Limit must be between 1 and 100.');
        }

        if (null !== $stage && !\in_array($stage, self::stages(), true)) {
            throw new \InvalidArgumentException(\sprintf('Unsupported stage "%s".', $stage));
        }
    }

    /**
     * @return list<string>
     */
    public static function stages(): array
    {
        return [self::STAGE_IN_PROGRESS, self::STAGE_TO_CLOSE, self::STAGE_CLOSED];
    }

    /**
     * @return list<string>
     */
    public static function kinds(): array
    {
        return [self::KIND_URGENT, self::KIND_SURGERY, self::KIND_LAB];
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->limit;
    }

    /**
     * True when a restriction is an empty set, so the repository can answer
     * without touching the database (MySQL refuses an empty IN () anyway).
     */
    public function isImpossible(): bool
    {
        return [] === $this->restrictToAdmissionIds
            || [] === $this->restrictToActCodes
            || [] === $this->consultationIds;
    }
}
