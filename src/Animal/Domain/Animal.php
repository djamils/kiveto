<?php

declare(strict_types=1);

namespace App\Animal\Domain;

use App\Animal\Domain\Event\AnimalArchived;
use App\Animal\Domain\Event\AnimalCreated;
use App\Animal\Domain\Exception\AnimalAlreadyArchivedException;
use App\Animal\Domain\Exception\AnimalArchivedCannotBeModifiedException;
use App\Animal\Domain\Exception\AnimalMustHavePrimaryOwnerException;
use App\Animal\Domain\Exception\DuplicateActiveOwnerException;
use App\Animal\Domain\Exception\PrimaryOwnerConflictException;
use App\Animal\Domain\ValueObject\AnimalId;
use App\Animal\Domain\ValueObject\AnimalStatus;
use App\Animal\Domain\ValueObject\AuxiliaryContact;
use App\Animal\Domain\ValueObject\Identification;
use App\Animal\Domain\ValueObject\LifeCycle;
use App\Animal\Domain\ValueObject\Ownership;
use App\Animal\Domain\ValueObject\OwnershipRole;
use App\Animal\Domain\ValueObject\OwnershipStatus;
use App\Animal\Domain\ValueObject\ReproductiveStatus;
use App\Animal\Domain\ValueObject\Sex;
use App\Animal\Domain\ValueObject\Species;
use App\Animal\Domain\ValueObject\Transfer;
use App\Clinic\Domain\ValueObject\ClinicId;
use App\Shared\Domain\Aggregate\AggregateRoot;

final class Animal extends AggregateRoot
{
    /** @var list<Ownership> */
    private array $ownerships = [];

    private function __construct(
        private readonly AnimalId $id,
        private readonly ClinicId $clinicId,
        private string $name,
        private Species $species,
        private Sex $sex,
        private ReproductiveStatus $reproductiveStatus,
        private bool $isMixedBreed,
        private ?string $breedName,
        private ?\DateTimeImmutable $birthDate,
        private ?string $color,
        private ?string $photoUrl,
        private Identification $identification,
        private LifeCycle $lifeCycle,
        private Transfer $transfer,
        private ?AuxiliaryContact $auxiliaryContact,
        private AnimalStatus $status,
        private readonly \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt,
    ) {
    }

    /**
     * @param list<string> $secondaryOwnerClientIds
     */
    public static function create(
        AnimalId $id,
        ClinicId $clinicId,
        string $name,
        Species $species,
        Sex $sex,
        ReproductiveStatus $reproductiveStatus,
        bool $isMixedBreed,
        ?string $breedName,
        ?\DateTimeImmutable $birthDate,
        ?string $color,
        ?string $photoUrl,
        Identification $identification,
        LifeCycle $lifeCycle,
        Transfer $transfer,
        ?AuxiliaryContact $auxiliaryContact,
        string $primaryOwnerClientId,
        array $secondaryOwnerClientIds,
        \DateTimeImmutable $now,
    ): self {
        $identification->ensureConsistency();
        $lifeCycle->ensureConsistency();
        $transfer->ensureConsistency();

        $animal = new self(
            id: $id,
            clinicId: $clinicId,
            name: $name,
            species: $species,
            sex: $sex,
            reproductiveStatus: $reproductiveStatus,
            isMixedBreed: $isMixedBreed,
            breedName: $breedName,
            birthDate: $birthDate,
            color: $color,
            photoUrl: $photoUrl,
            identification: $identification,
            lifeCycle: $lifeCycle,
            transfer: $transfer,
            auxiliaryContact: $auxiliaryContact,
            status: AnimalStatus::ACTIVE,
            createdAt: $now,
            updatedAt: $now,
        );

        // Create primary ownership
        $animal->ownerships[] = new Ownership(
            clientId: $primaryOwnerClientId,
            role: OwnershipRole::PRIMARY,
            status: OwnershipStatus::ACTIVE,
            startedAt: $now,
            endedAt: null,
        );

        // Create secondary ownerships
        foreach ($secondaryOwnerClientIds as $clientId) {
            $animal->ownerships[] = new Ownership(
                clientId: $clientId,
                role: OwnershipRole::SECONDARY,
                status: OwnershipStatus::ACTIVE,
                startedAt: $now,
                endedAt: null,
            );
        }

        $animal->recordDomainEvent(new AnimalCreated(
            animalId: $id->toString(),
            clinicId: $clinicId->toString(),
            name: $name,
            primaryOwnerClientId: $primaryOwnerClientId,
        ));

        return $animal;
    }

    public function id(): AnimalId
    {
        return $this->id;
    }

    public function clinicId(): ClinicId
    {
        return $this->clinicId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function species(): Species
    {
        return $this->species;
    }

    public function sex(): Sex
    {
        return $this->sex;
    }

    public function reproductiveStatus(): ReproductiveStatus
    {
        return $this->reproductiveStatus;
    }

    public function isMixedBreed(): bool
    {
        return $this->isMixedBreed;
    }

    public function breedName(): ?string
    {
        return $this->breedName;
    }

    public function birthDate(): ?\DateTimeImmutable
    {
        return $this->birthDate;
    }

    public function color(): ?string
    {
        return $this->color;
    }

    public function photoUrl(): ?string
    {
        return $this->photoUrl;
    }

    public function identification(): Identification
    {
        return $this->identification;
    }

    public function lifeCycle(): LifeCycle
    {
        return $this->lifeCycle;
    }

    public function transfer(): Transfer
    {
        return $this->transfer;
    }

    public function auxiliaryContact(): ?AuxiliaryContact
    {
        return $this->auxiliaryContact;
    }

    public function status(): AnimalStatus
    {
        return $this->status;
    }

    /** @return list<Ownership> */
    public function ownerships(): array
    {
        return $this->ownerships;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function updateIdentity(
        string $name,
        Species $species,
        Sex $sex,
        ReproductiveStatus $reproductiveStatus,
        bool $isMixedBreed,
        ?string $breedName,
        ?\DateTimeImmutable $birthDate,
        ?string $color,
        ?string $photoUrl,
        Identification $identification,
        ?AuxiliaryContact $auxiliaryContact,
        \DateTimeImmutable $now,
    ): void {
        $this->ensureNotArchived();

        $identification->ensureConsistency();

        $this->name               = $name;
        $this->species            = $species;
        $this->sex                = $sex;
        $this->reproductiveStatus = $reproductiveStatus;
        $this->isMixedBreed       = $isMixedBreed;
        $this->breedName          = $breedName;
        $this->birthDate          = $birthDate;
        $this->color              = $color;
        $this->photoUrl           = $photoUrl;
        $this->identification     = $identification;
        $this->auxiliaryContact   = $auxiliaryContact;
        $this->updatedAt          = $now;
    }

    public function updateLifeCycle(LifeCycle $lifeCycle, \DateTimeImmutable $now): void
    {
        $this->ensureNotArchived();
        $lifeCycle->ensureConsistency();

        $this->lifeCycle = $lifeCycle;
        $this->updatedAt = $now;
    }

    public function updateTransfer(Transfer $transfer, \DateTimeImmutable $now): void
    {
        $this->ensureNotArchived();
        $transfer->ensureConsistency();

        $this->transfer  = $transfer;
        $this->updatedAt = $now;
    }

    /**
     * @param list<string> $secondaryOwnerClientIds
     */
    public function replaceOwners(
        string $primaryOwnerClientId,
        array $secondaryOwnerClientIds,
        \DateTimeImmutable $now,
    ): void {
        $this->ensureNotArchived();

        // End all current active ownerships
        foreach ($this->ownerships as $key => $ownership) {
            if ($ownership->isActive()) {
                $this->ownerships[$key] = $ownership->end($now);
            }
        }

        // Create new primary ownership
        $this->ownerships[] = new Ownership(
            clientId: $primaryOwnerClientId,
            role: OwnershipRole::PRIMARY,
            status: OwnershipStatus::ACTIVE,
            startedAt: $now,
            endedAt: null,
        );

        // Create new secondary ownerships
        foreach ($secondaryOwnerClientIds as $clientId) {
            if ($clientId === $primaryOwnerClientId) {
                throw new DuplicateActiveOwnerException($this->id->toString(), $clientId);
            }

            $this->ownerships[] = new Ownership(
                clientId: $clientId,
                role: OwnershipRole::SECONDARY,
                status: OwnershipStatus::ACTIVE,
                startedAt: $now,
                endedAt: null,
            );
        }

        $this->updatedAt = $now;
    }

    public function archive(\DateTimeImmutable $now): void
    {
        if (AnimalStatus::ARCHIVED === $this->status) {
            throw new AnimalAlreadyArchivedException($this->id->toString());
        }

        $this->status    = AnimalStatus::ARCHIVED;
        $this->updatedAt = $now;

        $this->recordDomainEvent(new AnimalArchived(
            animalId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
        ));
    }

    /**
     * Resolve ownerships when a client is archived.
     * This method is called by the integration event consumer.
     */
    public function resolveOwnershipForArchivedClient(string $archivedClientId, \DateTimeImmutable $now): void
    {
        $activeOwnerships   = array_filter($this->ownerships, static fn (Ownership $o) => $o->isActive());
        $concernedOwnership = null;
        $concernedKey       = null;

        // Find the concerned ownership
        foreach ($this->ownerships as $key => $ownership) {
            if ($ownership->isActive() && $ownership->clientId === $archivedClientId) {
                $concernedOwnership = $ownership;
                $concernedKey       = $key;
                break;
            }
        }

        if (null === $concernedOwnership) {
            // Client is not an active owner, nothing to do (idempotence)
            return;
        }

        \assert(\is_int($concernedKey), 'If ownership exists, its key must be an int');
        // If SECONDARY owner, just end the ownership
        if ($concernedOwnership->isSecondary()) {
            $this->ownerships[$concernedKey] = $concernedOwnership->end($now); /** @phpstan-ignore assign.propertyType */ // phpcs:ignore
            /** @var list<Ownership> $ownerships */
            $ownerships       = array_values($this->ownerships);
            $this->ownerships = $ownerships;
            $this->updatedAt  = $now;

            return;
        }

        // If PRIMARY owner, we need to handle it
        \assert($concernedOwnership->isPrimary());

        // Find active secondary owners
        $activeSecondaries = array_filter(
            $activeOwnerships,
            static fn (Ownership $o) => $o->isSecondary() && $o->clientId !== $archivedClientId
        );

        if ([] !== $activeSecondaries) {
            // Promote the oldest secondary to primary
            usort($activeSecondaries, static function (Ownership $a, Ownership $b): int {
                $cmp = $a->startedAt <=> $b->startedAt;
                if (0 !== $cmp) {
                    return $cmp;
                }

                // For determinism, compare clientId as fallback
                return $a->clientId <=> $b->clientId;
            });

            $toPromote = $activeSecondaries[0];

            // End the old primary
            $this->ownerships[$concernedKey] = $concernedOwnership->end($now); /* @phpstan-ignore assign.propertyType */

            // Promote the secondary
            foreach ($this->ownerships as $key => $ownership) {
                if ($ownership->isActive() && $ownership->clientId === $toPromote->clientId) {
                    $this->ownerships[$key] = $ownership->end($now);
                    $this->ownerships[]     = new Ownership(
                        clientId: $toPromote->clientId,
                        role: OwnershipRole::PRIMARY,
                        status: OwnershipStatus::ACTIVE,
                        startedAt: $now,
                        endedAt: null,
                    );
                    break;
                }
            }

            /** @var list<Ownership> $ownerships */
            $ownerships       = array_values($this->ownerships);
            $this->ownerships = $ownerships;
            $this->updatedAt  = $now;
        } else {
            // No secondary owner => archive the animal
            $this->ownerships[$concernedKey] = $concernedOwnership->end($now); /** @phpstan-ignore assign.propertyType */ // phpcs:ignore
            /** @var list<Ownership> $ownerships */
            $ownerships       = array_values($this->ownerships);
            $this->ownerships = $ownerships;
            $this->status     = AnimalStatus::ARCHIVED;
            $this->updatedAt  = $now;

            $this->recordDomainEvent(new AnimalArchived(
                animalId: $this->id->toString(),
                clinicId: $this->clinicId->toString(),
            ));
        }
    }

    public function ensureInvariants(): void
    {
        $this->identification->ensureConsistency();
        $this->lifeCycle->ensureConsistency();
        $this->transfer->ensureConsistency();

        if (AnimalStatus::ACTIVE === $this->status) {
            $this->ensureHasExactlyOnePrimaryOwner();
            $this->ensureNoDuplicateActiveOwners();
        }
    }

    // Reconstruction from persistence
    /**
     * @param list<Ownership> $ownerships
     */
    public static function reconstituteFromPersistence(
        AnimalId $id,
        ClinicId $clinicId,
        string $name,
        Species $species,
        Sex $sex,
        ReproductiveStatus $reproductiveStatus,
        bool $isMixedBreed,
        ?string $breedName,
        ?\DateTimeImmutable $birthDate,
        ?string $color,
        ?string $photoUrl,
        Identification $identification,
        LifeCycle $lifeCycle,
        Transfer $transfer,
        ?AuxiliaryContact $auxiliaryContact,
        AnimalStatus $status,
        array $ownerships,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ): self {
        $animal = new self(
            id: $id,
            clinicId: $clinicId,
            name: $name,
            species: $species,
            sex: $sex,
            reproductiveStatus: $reproductiveStatus,
            isMixedBreed: $isMixedBreed,
            breedName: $breedName,
            birthDate: $birthDate,
            color: $color,
            photoUrl: $photoUrl,
            identification: $identification,
            lifeCycle: $lifeCycle,
            transfer: $transfer,
            auxiliaryContact: $auxiliaryContact,
            status: $status,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );

        $animal->ownerships = $ownerships;

        return $animal;
    }

    private function ensureNotArchived(): void
    {
        if (AnimalStatus::ARCHIVED === $this->status) {
            throw new AnimalArchivedCannotBeModifiedException($this->id->toString());
        }
    }

    private function ensureHasExactlyOnePrimaryOwner(): void
    {
        $activePrimaries = array_filter(
            $this->ownerships,
            static fn (Ownership $o) => $o->isActive() && $o->isPrimary()
        );

        if (1 !== \count($activePrimaries)) {
            throw new AnimalMustHavePrimaryOwnerException($this->id->toString());
        }
    }

    private function ensureNoDuplicateActiveOwners(): void
    {
        $activeOwnerships = array_filter($this->ownerships, static fn (Ownership $o) => $o->isActive());
        $clientIds        = array_map(static fn (Ownership $o) => $o->clientId, $activeOwnerships);

        if (\count($clientIds) !== \count(array_unique($clientIds))) {
            throw new PrimaryOwnerConflictException($this->id->toString());
        }
    }
}
