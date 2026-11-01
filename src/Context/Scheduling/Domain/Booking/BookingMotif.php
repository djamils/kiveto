<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\Booking;

use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\PlanningBlockType;
use App\Context\Scheduling\Domain\ValueObject\UserId;
use App\Shared\Domain\Aggregate\AggregateRoot;

/**
 * What a client can book online: its duration, the planning blocks it may
 * sit in and the practitioners who take it (S4b). A new client always needs
 * the clinic's confirmation, so there is no new-client confirmation mode (D15).
 * Whether each practitioner is still eligible is checked by the application
 * through `MembershipEligibilityCheckerInterface`.
 */
final class BookingMotif extends AggregateRoot
{
    public const int LABEL_MAX_LENGTH       = 80;
    public const int DESCRIPTION_MAX_LENGTH = 500;
    public const int DURATION_MIN           = 5;
    public const int DURATION_MAX           = 240;
    public const int DURATION_STEP          = 5;

    private BookingMotifId $id;
    private ClinicId $clinicId;
    private string $label;
    private ?string $description;
    private int $durationMin;
    private bool $onlineEnabled;
    private ConfirmationMode $confirmationKnownClient;
    /** @var list<PlanningBlockType> */
    private array $allowedBlockTypes;
    /** @var list<UserId> */
    private array $practitionerIds;
    private ?BookingPreventiveCareKind $preventiveCareKind;
    private bool $newClientsAllowed;
    private int $sortOrder;
    private ?\DateTimeImmutable $archivedAt;

    private function __construct()
    {
    }

    /**
     * @param list<PlanningBlockType> $allowedBlockTypes
     * @param list<UserId>            $practitionerIds
     */
    public static function create(
        BookingMotifId $id,
        ClinicId $clinicId,
        string $label,
        ?string $description,
        int $durationMin,
        bool $onlineEnabled,
        ConfirmationMode $confirmationKnownClient,
        array $allowedBlockTypes,
        array $practitionerIds,
        ?BookingPreventiveCareKind $preventiveCareKind,
        bool $newClientsAllowed,
        int $sortOrder,
    ): self {
        $motif             = new self();
        $motif->id         = $id;
        $motif->clinicId   = $clinicId;
        $motif->archivedAt = null;
        $motif->update(
            $label,
            $description,
            $durationMin,
            $onlineEnabled,
            $confirmationKnownClient,
            $allowedBlockTypes,
            $practitionerIds,
            $preventiveCareKind,
            $newClientsAllowed,
            $sortOrder,
        );

        return $motif;
    }

    /**
     * @param list<PlanningBlockType> $allowedBlockTypes
     * @param list<UserId>            $practitionerIds
     */
    public static function reconstitute(
        BookingMotifId $id,
        ClinicId $clinicId,
        string $label,
        ?string $description,
        int $durationMin,
        bool $onlineEnabled,
        ConfirmationMode $confirmationKnownClient,
        array $allowedBlockTypes,
        array $practitionerIds,
        ?BookingPreventiveCareKind $preventiveCareKind,
        bool $newClientsAllowed,
        int $sortOrder,
        ?\DateTimeImmutable $archivedAt,
    ): self {
        $motif                          = new self();
        $motif->id                      = $id;
        $motif->clinicId                = $clinicId;
        $motif->label                   = $label;
        $motif->description             = $description;
        $motif->durationMin             = $durationMin;
        $motif->onlineEnabled           = $onlineEnabled;
        $motif->confirmationKnownClient = $confirmationKnownClient;
        $motif->allowedBlockTypes       = $allowedBlockTypes;
        $motif->practitionerIds         = $practitionerIds;
        $motif->preventiveCareKind      = $preventiveCareKind;
        $motif->newClientsAllowed       = $newClientsAllowed;
        $motif->sortOrder               = $sortOrder;
        $motif->archivedAt              = $archivedAt;

        return $motif;
    }

    /**
     * @param list<PlanningBlockType> $allowedBlockTypes
     * @param list<UserId>            $practitionerIds
     *
     * @throws InvalidBookingConfigurationException
     */
    public function update(
        string $label,
        ?string $description,
        int $durationMin,
        bool $onlineEnabled,
        ConfirmationMode $confirmationKnownClient,
        array $allowedBlockTypes,
        array $practitionerIds,
        ?BookingPreventiveCareKind $preventiveCareKind,
        bool $newClientsAllowed,
        int $sortOrder,
    ): void {
        $this->label                   = self::validLabel($label);
        $this->description             = self::validDescription($description);
        $this->durationMin             = self::validDuration($durationMin);
        $this->onlineEnabled           = $onlineEnabled;
        $this->confirmationKnownClient = $confirmationKnownClient;
        $this->allowedBlockTypes       = self::validBlockTypes($allowedBlockTypes);
        $this->practitionerIds         = self::validPractitioners($practitionerIds);
        $this->preventiveCareKind      = $preventiveCareKind;
        $this->newClientsAllowed       = $newClientsAllowed;
        $this->sortOrder               = $sortOrder;
    }

    /**
     * Archiving twice keeps the first date.
     */
    public function archive(\DateTimeImmutable $at): void
    {
        $this->archivedAt ??= $at;
    }

    public function id(): BookingMotifId
    {
        return $this->id;
    }

    public function clinicId(): ClinicId
    {
        return $this->clinicId;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function durationMin(): int
    {
        return $this->durationMin;
    }

    public function isOnlineEnabled(): bool
    {
        return $this->onlineEnabled;
    }

    public function confirmationKnownClient(): ConfirmationMode
    {
        return $this->confirmationKnownClient;
    }

    /**
     * @return list<PlanningBlockType>
     */
    public function allowedBlockTypes(): array
    {
        return $this->allowedBlockTypes;
    }

    public function allowsBlockType(PlanningBlockType $type): bool
    {
        return \in_array($type, $this->allowedBlockTypes, true);
    }

    /**
     * @return list<UserId>
     */
    public function practitionerIds(): array
    {
        return $this->practitionerIds;
    }

    public function isBookableWith(UserId $practitionerId): bool
    {
        foreach ($this->practitionerIds as $id) {
            if ($id->equals($practitionerId)) {
                return true;
            }
        }

        return false;
    }

    public function preventiveCareKind(): ?BookingPreventiveCareKind
    {
        return $this->preventiveCareKind;
    }

    public function newClientsAllowed(): bool
    {
        return $this->newClientsAllowed;
    }

    public function sortOrder(): int
    {
        return $this->sortOrder;
    }

    public function archivedAt(): ?\DateTimeImmutable
    {
        return $this->archivedAt;
    }

    public function isArchived(): bool
    {
        return null !== $this->archivedAt;
    }

    private static function validLabel(string $label): string
    {
        $label = mb_trim($label);

        if ('' === $label) {
            throw new InvalidBookingConfigurationException('label', 'Le libellé du motif est obligatoire.');
        }

        if (mb_strlen($label) > self::LABEL_MAX_LENGTH) {
            throw new InvalidBookingConfigurationException('label', \sprintf('Le libellé du motif ne peut pas dépasser %d caractères.', self::LABEL_MAX_LENGTH));
        }

        return $label;
    }

    private static function validDescription(?string $description): ?string
    {
        $description = mb_trim($description ?? '');

        if (mb_strlen($description) > self::DESCRIPTION_MAX_LENGTH) {
            throw new InvalidBookingConfigurationException('description', \sprintf('La description ne peut pas dépasser %d caractères.', self::DESCRIPTION_MAX_LENGTH));
        }

        return '' === $description ? null : $description;
    }

    private static function validDuration(int $durationMin): int
    {
        if ($durationMin < self::DURATION_MIN || $durationMin > self::DURATION_MAX) {
            throw new InvalidBookingConfigurationException('durationMin', \sprintf('La durée doit être comprise entre %d et %d minutes.', self::DURATION_MIN, self::DURATION_MAX));
        }

        if (0 !== $durationMin % self::DURATION_STEP) {
            throw new InvalidBookingConfigurationException('durationMin', \sprintf('La durée doit être un multiple de %d minutes.', self::DURATION_STEP));
        }

        return $durationMin;
    }

    /**
     * @param list<PlanningBlockType> $types
     *
     * @return list<PlanningBlockType>
     */
    private static function validBlockTypes(array $types): array
    {
        $unique = [];

        foreach ($types as $type) {
            if (!$type->acceptsAppointments()) {
                throw new InvalidBookingConfigurationException('allowedBlockTypes', 'Ce type de plage n\'accepte pas de rendez-vous.');
            }

            $unique[$type->value] = $type;
        }

        if ([] === $unique) {
            throw new InvalidBookingConfigurationException('allowedBlockTypes', 'Choisissez au moins un type de plage.');
        }

        return array_values($unique);
    }

    /**
     * @param list<UserId> $practitionerIds
     *
     * @return list<UserId>
     */
    private static function validPractitioners(array $practitionerIds): array
    {
        $unique = [];

        foreach ($practitionerIds as $id) {
            $unique[$id->toString()] = $id;
        }

        if ([] === $unique) {
            throw new InvalidBookingConfigurationException('practitionerIds', 'Choisissez au moins un praticien.');
        }

        return array_values($unique);
    }
}
