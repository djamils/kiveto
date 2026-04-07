<?php

declare(strict_types=1);

namespace App\Tests\Unit\Animal\Domain;

use App\Animal\Domain\Animal;
use App\Animal\Domain\Event\AnimalArchived;
use App\Animal\Domain\Event\AnimalCreated;
use App\Animal\Domain\Exception\AnimalAlreadyArchivedException;
use App\Animal\Domain\Exception\AnimalMustHavePrimaryOwnerException;
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
use PHPUnit\Framework\TestCase;

final class AnimalTest extends TestCase
{
    public function testCreate(): void
    {
        $id             = AnimalId::fromString('01234567-89ab-cdef-0123-456789abcdef');
        $clinicId       = ClinicId::fromString('12345678-9abc-def0-1234-56789abcdef0');
        $birthDate      = new \DateTimeImmutable('2020-01-01');
        $identification = Identification::createEmpty()->withMicrochip('123456789');
        $lifeCycle      = LifeCycle::alive();
        $transfer       = Transfer::none();
        $auxContact     = new AuxiliaryContact('John', 'Doe', '+33612345678');
        $now            = new \DateTimeImmutable('2024-01-01T10:00:00+00:00');

        $animal = Animal::create(
            id: $id,
            clinicId: $clinicId,
            name: 'Rex',
            species: Species::DOG,
            sex: Sex::MALE,
            reproductiveStatus: ReproductiveStatus::INTACT,
            isMixedBreed: false,
            breedName: 'Labrador',
            birthDate: $birthDate,
            color: 'Golden',
            photoUrl: 'https://example.com/photo.jpg',
            identification: $identification,
            lifeCycle: $lifeCycle,
            transfer: $transfer,
            auxiliaryContact: $auxContact,
            primaryOwnerClientId: 'client-123',
            secondaryOwnerClientIds: [],
            now: $now
        );

        // Test all getters
        self::assertSame($id, $animal->id());
        self::assertSame($clinicId, $animal->clinicId());
        self::assertSame('Rex', $animal->name());
        self::assertSame(Species::DOG, $animal->species());
        self::assertSame(Sex::MALE, $animal->sex());
        self::assertSame(ReproductiveStatus::INTACT, $animal->reproductiveStatus());
        self::assertFalse($animal->isMixedBreed());
        self::assertSame('Labrador', $animal->breedName());
        self::assertSame($birthDate, $animal->birthDate());
        self::assertSame('Golden', $animal->color());
        self::assertSame('https://example.com/photo.jpg', $animal->photoUrl());
        self::assertSame($identification, $animal->identification());
        self::assertSame($lifeCycle, $animal->lifeCycle());
        self::assertSame($transfer, $animal->transfer());
        self::assertSame($auxContact, $animal->auxiliaryContact());
        self::assertSame(AnimalStatus::ACTIVE, $animal->status());
        self::assertSame($now, $animal->createdAt());
        self::assertSame($now, $animal->updatedAt());
        self::assertCount(1, $animal->ownerships());

        $events = $animal->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(AnimalCreated::class, $events[0]);
    }

    public function testArchive(): void
    {
        $animal = $this->createMinimalAnimal();
        $now    = new \DateTimeImmutable('2024-06-01T10:00:00+00:00');

        $animal->archive($now);

        self::assertSame(AnimalStatus::ARCHIVED, $animal->status());

        $events = $animal->pullDomainEvents();
        self::assertCount(2, $events); // Created + Archived
        self::assertInstanceOf(AnimalArchived::class, $events[1]);
    }

    public function testArchiveThrowsWhenAlreadyArchived(): void
    {
        $animal = $this->createMinimalAnimal();
        $now    = new \DateTimeImmutable('2024-06-01T10:00:00+00:00');

        $animal->archive($now);

        $this->expectException(AnimalAlreadyArchivedException::class);

        $animal->archive($now);
    }

    public function testUpdateIdentity(): void
    {
        $animal = $this->createMinimalAnimal();
        $now    = new \DateTimeImmutable('2024-06-01T10:00:00+00:00');

        $animal->updateIdentity(
            name: 'Max',
            species: Species::CAT,
            sex: Sex::FEMALE,
            reproductiveStatus: ReproductiveStatus::NEUTERED,
            isMixedBreed: true,
            breedName: 'Mixed',
            birthDate: new \DateTimeImmutable('2021-01-01'),
            color: 'Black',
            photoUrl: 'https://example.com/photo.jpg',
            identification: Identification::createEmpty(),
            auxiliaryContact: null,
            now: $now
        );

        self::assertSame('Max', $animal->name());
        self::assertSame(Species::CAT, $animal->species());
        self::assertSame(Sex::FEMALE, $animal->sex());
        self::assertSame($now, $animal->updatedAt());
    }

    public function testUpdateLifeCycle(): void
    {
        $animal = $this->createMinimalAnimal();
        $now    = new \DateTimeImmutable('2024-06-01T10:00:00+00:00');

        $lifeCycle = new LifeCycle(
            lifeStatus: \App\Animal\Domain\ValueObject\LifeStatus::DECEASED,
            deceasedAt: $now,
            missingSince: null
        );

        $animal->updateLifeCycle($lifeCycle, $now);

        self::assertSame($lifeCycle, $animal->lifeCycle());
        self::assertSame($now, $animal->updatedAt());
    }

    public function testUpdateTransfer(): void
    {
        $animal = $this->createMinimalAnimal();
        $now    = new \DateTimeImmutable('2024-06-01T10:00:00+00:00');

        $transfer = new Transfer(
            transferStatus: \App\Animal\Domain\ValueObject\TransferStatus::SOLD,
            soldAt: $now,
            givenAt: null
        );

        $animal->updateTransfer($transfer, $now);

        self::assertSame($transfer, $animal->transfer());
        self::assertSame($now, $animal->updatedAt());
    }

    public function testReplaceOwners(): void
    {
        $animal = $this->createMinimalAnimal();
        $now    = new \DateTimeImmutable('2024-06-01T10:00:00+00:00');

        $animal->replaceOwners(
            primaryOwnerClientId: 'client-456',
            secondaryOwnerClientIds: ['client-789', 'client-999'],
            now: $now
        );

        $ownerships = $animal->ownerships();
        $active     = array_filter($ownerships, static fn ($o) => $o->isActive());

        self::assertCount(3, $active);

        // Check primary owner
        $primary = array_values(array_filter($active, static fn ($o) => $o->isPrimary()))[0];
        self::assertSame('client-456', $primary->clientId);

        // Check secondary owners
        $secondaries = array_values(array_filter($active, static fn ($o) => $o->isSecondary()));
        self::assertCount(2, $secondaries);
    }

    public function testReplaceOwnersThrowsOnDuplicate(): void
    {
        $animal = $this->createMinimalAnimal();
        $now    = new \DateTimeImmutable('2024-06-01T10:00:00+00:00');

        $this->expectException(\App\Animal\Domain\Exception\DuplicateActiveOwnerException::class);

        $animal->replaceOwners(
            primaryOwnerClientId: 'client-456',
            secondaryOwnerClientIds: ['client-456'], // Duplicate!
            now: $now
        );
    }

    public function testResolveOwnershipForArchivedClientWhenSecondary(): void
    {
        $animal = Animal::create(
            id: AnimalId::fromString('01234567-89ab-cdef-0123-456789abcdef'),
            clinicId: ClinicId::fromString('12345678-9abc-def0-1234-56789abcdef0'),
            name: 'Rex',
            species: Species::DOG,
            sex: Sex::MALE,
            reproductiveStatus: ReproductiveStatus::INTACT,
            isMixedBreed: false,
            breedName: null,
            birthDate: null,
            color: null,
            photoUrl: null,
            identification: Identification::createEmpty(),
            lifeCycle: LifeCycle::alive(),
            transfer: Transfer::none(),
            auxiliaryContact: null,
            primaryOwnerClientId: 'client-123',
            secondaryOwnerClientIds: ['client-456'],
            now: new \DateTimeImmutable('2024-01-01T10:00:00+00:00')
        );

        $now = new \DateTimeImmutable('2024-06-01T10:00:00+00:00');
        $animal->resolveOwnershipForArchivedClient('client-456', $now);

        // Animal should still be active (secondary removed)
        self::assertSame(AnimalStatus::ACTIVE, $animal->status());

        // Secondary should be ended
        $activeOwnerships = array_filter($animal->ownerships(), static fn ($o) => $o->isActive());
        self::assertCount(1, $activeOwnerships); // Only primary remains
    }

    public function testResolveOwnershipForArchivedClientPromotion(): void
    {
        $animal = Animal::create(
            id: AnimalId::fromString('01234567-89ab-cdef-0123-456789abcdef'),
            clinicId: ClinicId::fromString('12345678-9abc-def0-1234-56789abcdef0'),
            name: 'Rex',
            species: Species::DOG,
            sex: Sex::MALE,
            reproductiveStatus: ReproductiveStatus::INTACT,
            isMixedBreed: false,
            breedName: null,
            birthDate: null,
            color: null,
            photoUrl: null,
            identification: Identification::createEmpty(),
            lifeCycle: LifeCycle::alive(),
            transfer: Transfer::none(),
            auxiliaryContact: null,
            primaryOwnerClientId: 'client-123',
            secondaryOwnerClientIds: ['client-456'],
            now: new \DateTimeImmutable('2024-01-01T10:00:00+00:00')
        );

        $now = new \DateTimeImmutable('2024-06-01T10:00:00+00:00');
        $animal->resolveOwnershipForArchivedClient('client-123', $now);

        // Animal should still be active (secondary promoted)
        self::assertSame(AnimalStatus::ACTIVE, $animal->status());

        // client-456 should now be primary
        $activeOwnerships = array_filter($animal->ownerships(), static fn ($o) => $o->isActive());
        self::assertCount(1, $activeOwnerships);

        $primary = array_values(array_filter($activeOwnerships, static fn ($o) => $o->isPrimary()))[0];
        self::assertSame('client-456', $primary->clientId);
    }

    public function testResolveOwnershipForArchivedClientArchivesAnimal(): void
    {
        $animal = $this->createMinimalAnimal();

        $now = new \DateTimeImmutable('2024-06-01T10:00:00+00:00');
        $animal->resolveOwnershipForArchivedClient('client-123', $now);

        // Animal should be archived (no secondary to promote)
        self::assertSame(AnimalStatus::ARCHIVED, $animal->status());

        // Should have emitted AnimalArchived event
        $events = $animal->pullDomainEvents();
        self::assertCount(2, $events); // Created + Archived
        self::assertInstanceOf(AnimalArchived::class, $events[1]);
    }

    public function testResolveOwnershipIsIdempotent(): void
    {
        $animal = $this->createMinimalAnimal();
        $now    = new \DateTimeImmutable('2024-06-01T10:00:00+00:00');

        // Call twice with non-owner client
        $animal->resolveOwnershipForArchivedClient('client-999', $now);
        $animal->resolveOwnershipForArchivedClient('client-999', $now);

        // Animal should still be active, nothing changed
        self::assertSame(AnimalStatus::ACTIVE, $animal->status());
    }

    public function testUpdateIdentityThrowsWhenArchived(): void
    {
        $animal = $this->createMinimalAnimal();
        $now    = new \DateTimeImmutable('2024-06-01T10:00:00+00:00');

        $animal->archive($now);

        $this->expectException(\App\Animal\Domain\Exception\AnimalArchivedCannotBeModifiedException::class);

        $animal->updateIdentity(
            name: 'Max',
            species: Species::CAT,
            sex: Sex::FEMALE,
            reproductiveStatus: ReproductiveStatus::NEUTERED,
            isMixedBreed: false,
            breedName: null,
            birthDate: null,
            color: null,
            photoUrl: null,
            identification: Identification::createEmpty(),
            auxiliaryContact: null,
            now: $now
        );
    }

    public function testEnsureInvariants(): void
    {
        $animal = $this->createMinimalAnimal();

        $animal->ensureInvariants();

        $this->addToAssertionCount(1); // No exception thrown
    }

    public function testResolveOwnershipPromotesOldestSecondary(): void
    {
        // Test the sorting logic (lines 387-393)
        $baseTime = new \DateTimeImmutable('2024-01-01T10:00:00+00:00');

        $animal = Animal::create(
            id: AnimalId::fromString('01234567-89ab-cdef-0123-456789abcdef'),
            clinicId: ClinicId::fromString('12345678-9abc-def0-1234-56789abcdef0'),
            name: 'Rex',
            species: Species::DOG,
            sex: Sex::MALE,
            reproductiveStatus: ReproductiveStatus::INTACT,
            isMixedBreed: false,
            breedName: null,
            birthDate: null,
            color: null,
            photoUrl: null,
            identification: Identification::createEmpty(),
            lifeCycle: LifeCycle::alive(),
            transfer: Transfer::none(),
            auxiliaryContact: null,
            primaryOwnerClientId: 'client-primary',
            secondaryOwnerClientIds: [],
            now: $baseTime
        );

        // Add secondary owners at different times
        $animal->replaceOwners(
            primaryOwnerClientId: 'client-primary',
            secondaryOwnerClientIds: ['client-b', 'client-a'], // a and b will have same startedAt
            now: $baseTime->modify('+1 hour')
        );

        // Archive primary owner - should promote by clientId (deterministic fallback)
        $animal->resolveOwnershipForArchivedClient('client-primary', $baseTime->modify('+2 hours'));

        $activeOwnerships = array_filter($animal->ownerships(), static fn ($o) => $o->isActive());
        $primary          = array_values(array_filter($activeOwnerships, static fn ($o) => $o->isPrimary()))[0];

        // Should promote 'client-a' (alphabetically first when startedAt is equal)
        self::assertSame('client-a', $primary->clientId);
    }

    public function testEnsureInvariantsThrowsWhenDuplicateActiveOwners(): void
    {
        $this->expectException(\App\Animal\Domain\Exception\PrimaryOwnerConflictException::class);

        $baseTime = new \DateTimeImmutable('2024-01-01T10:00:00+00:00');

        // Create animal with duplicate active owners (same clientId twice)
        $animal = Animal::reconstituteFromPersistence(
            id: AnimalId::fromString('01234567-89ab-cdef-0123-456789abcdef'),
            clinicId: ClinicId::fromString('12345678-9abc-def0-1234-56789abcdef0'),
            name: 'Rex',
            species: Species::DOG,
            sex: Sex::MALE,
            reproductiveStatus: ReproductiveStatus::INTACT,
            isMixedBreed: false,
            breedName: null,
            birthDate: null,
            color: null,
            photoUrl: null,
            identification: Identification::createEmpty(),
            lifeCycle: LifeCycle::alive(),
            transfer: Transfer::none(),
            auxiliaryContact: null,
            status: AnimalStatus::ACTIVE,
            ownerships: [
                new Ownership(
                    'client-same',
                    OwnershipRole::PRIMARY,
                    OwnershipStatus::ACTIVE,
                    $baseTime,
                    null
                ),
                new Ownership(
                    'client-same',
                    OwnershipRole::SECONDARY,
                    OwnershipStatus::ACTIVE,
                    $baseTime,
                    null
                ), // Duplicate!
            ],
            createdAt: $baseTime,
            updatedAt: $baseTime,
        );

        // Should throw PrimaryOwnerConflictException
        $animal->ensureInvariants();
    }

    public function testEnsureInvariantsThrowsWhenMultiplePrimaryOwners(): void
    {
        // This should throw AnimalMustHavePrimaryOwnerException via ensureHasExactlyOnePrimaryOwner
        // Actually, let me check what happens with 2 PRIMARY owners
        $this->expectException(AnimalMustHavePrimaryOwnerException::class);

        $baseTime = new \DateTimeImmutable('2024-01-01T10:00:00+00:00');

        $animal = Animal::reconstituteFromPersistence(
            id: AnimalId::fromString('01234567-89ab-cdef-0123-456789abcdef'),
            clinicId: ClinicId::fromString('12345678-9abc-def0-1234-56789abcdef0'),
            name: 'Rex',
            species: Species::DOG,
            sex: Sex::MALE,
            reproductiveStatus: ReproductiveStatus::INTACT,
            isMixedBreed: false,
            breedName: null,
            birthDate: null,
            color: null,
            photoUrl: null,
            identification: Identification::createEmpty(),
            lifeCycle: LifeCycle::alive(),
            transfer: Transfer::none(),
            auxiliaryContact: null,
            status: AnimalStatus::ACTIVE,
            ownerships: [
                new Ownership(
                    'client-a',
                    OwnershipRole::PRIMARY,
                    OwnershipStatus::ACTIVE,
                    $baseTime,
                    null
                ),
                new Ownership(
                    'client-b',
                    OwnershipRole::PRIMARY,
                    OwnershipStatus::ACTIVE,
                    $baseTime,
                    null
                ), // 2 PRIMARY!
            ],
            createdAt: $baseTime,
            updatedAt: $baseTime,
        );

        // Should throw (multiple PRIMARY = not exactly 1)
        $animal->ensureInvariants();
    }

    public function testReconstitute(): void
    {
        $id        = AnimalId::fromString('01234567-89ab-cdef-0123-456789abcdef');
        $clinicId  = ClinicId::fromString('12345678-9abc-def0-1234-56789abcdef0');
        $createdAt = new \DateTimeImmutable('2024-01-01T10:00:00+00:00');
        $updatedAt = new \DateTimeImmutable('2024-06-01T10:00:00+00:00');

        $animal = Animal::reconstituteFromPersistence(
            id: $id,
            clinicId: $clinicId,
            name: 'Rex',
            species: Species::DOG,
            sex: Sex::MALE,
            reproductiveStatus: ReproductiveStatus::INTACT,
            isMixedBreed: false,
            breedName: null,
            birthDate: null,
            color: null,
            photoUrl: null,
            identification: Identification::createEmpty(),
            lifeCycle: LifeCycle::alive(),
            transfer: Transfer::none(),
            auxiliaryContact: null,
            status: AnimalStatus::ACTIVE,
            ownerships: [
                new Ownership(
                    clientId: 'client-123',
                    role: OwnershipRole::PRIMARY,
                    status: OwnershipStatus::ACTIVE,
                    startedAt: $createdAt,
                    endedAt: null
                ),
            ],
            createdAt: $createdAt,
            updatedAt: $updatedAt
        );

        self::assertSame($id, $animal->id());
        self::assertSame('Rex', $animal->name());
        self::assertSame(AnimalStatus::ACTIVE, $animal->status());
        self::assertSame($createdAt, $animal->createdAt());
        self::assertSame($updatedAt, $animal->updatedAt());

        // Should NOT emit domain events
        $events = $animal->pullDomainEvents();
        self::assertCount(0, $events);
    }

    public function testAllGetters(): void
    {
        $birthDate      = new \DateTimeImmutable('2020-01-01');
        $identification = Identification::createEmpty()->withMicrochip('123456789');
        $lifeCycle      = LifeCycle::alive();
        $transfer       = Transfer::none();
        $auxContact     = new AuxiliaryContact('John', 'Doe', '+33612345678');

        $animal = Animal::create(
            id: AnimalId::fromString('01234567-89ab-cdef-0123-456789abcdef'),
            clinicId: ClinicId::fromString('12345678-9abc-def0-1234-56789abcdef0'),
            name: 'Rex',
            species: Species::DOG,
            sex: Sex::MALE,
            reproductiveStatus: ReproductiveStatus::INTACT,
            isMixedBreed: false,
            breedName: 'Labrador',
            birthDate: $birthDate,
            color: 'Golden',
            photoUrl: 'https://example.com/photo.jpg',
            identification: $identification,
            lifeCycle: $lifeCycle,
            transfer: $transfer,
            auxiliaryContact: $auxContact,
            primaryOwnerClientId: 'client-123',
            secondaryOwnerClientIds: [],
            now: new \DateTimeImmutable('2024-01-01T10:00:00+00:00')
        );

        // Test all getters
        self::assertSame('Rex', $animal->name());
        self::assertSame(Species::DOG, $animal->species());
        self::assertSame(Sex::MALE, $animal->sex());
        self::assertSame(ReproductiveStatus::INTACT, $animal->reproductiveStatus());
        self::assertFalse($animal->isMixedBreed());
        self::assertSame('Labrador', $animal->breedName());
        self::assertSame($birthDate, $animal->birthDate());
        self::assertSame('Golden', $animal->color());
        self::assertSame('https://example.com/photo.jpg', $animal->photoUrl());
        self::assertSame($identification, $animal->identification());
        self::assertSame($lifeCycle, $animal->lifeCycle());
        self::assertSame($transfer, $animal->transfer());
        self::assertSame($auxContact, $animal->auxiliaryContact());
        self::assertCount(1, $animal->ownerships());
    }

    public function testEnsureInvariantsThrowsWhenNoPrimaryOwner(): void
    {
        // Create animal and manually break invariant
        $animal = Animal::reconstituteFromPersistence(
            id: AnimalId::fromString('01234567-89ab-cdef-0123-456789abcdef'),
            clinicId: ClinicId::fromString('12345678-9abc-def0-1234-56789abcdef0'),
            name: 'Rex',
            species: Species::DOG,
            sex: Sex::MALE,
            reproductiveStatus: ReproductiveStatus::INTACT,
            isMixedBreed: false,
            breedName: null,
            birthDate: null,
            color: null,
            photoUrl: null,
            identification: Identification::createEmpty(),
            lifeCycle: LifeCycle::alive(),
            transfer: Transfer::none(),
            auxiliaryContact: null,
            status: AnimalStatus::ACTIVE,
            ownerships: [], // NO PRIMARY!
            createdAt: new \DateTimeImmutable('2024-01-01T10:00:00+00:00'),
            updatedAt: new \DateTimeImmutable('2024-01-01T10:00:00+00:00')
        );

        $this->expectException(AnimalMustHavePrimaryOwnerException::class);

        $animal->ensureInvariants();
    }

    public function testEnsureInvariantsThrowsWhenDuplicateOwners(): void
    {
        // Create animal with duplicate owners
        $now = new \DateTimeImmutable('2024-01-01T10:00:00+00:00');

        $animal = Animal::reconstituteFromPersistence(
            id: AnimalId::fromString('01234567-89ab-cdef-0123-456789abcdef'),
            clinicId: ClinicId::fromString('12345678-9abc-def0-1234-56789abcdef0'),
            name: 'Rex',
            species: Species::DOG,
            sex: Sex::MALE,
            reproductiveStatus: ReproductiveStatus::INTACT,
            isMixedBreed: false,
            breedName: null,
            birthDate: null,
            color: null,
            photoUrl: null,
            identification: Identification::createEmpty(),
            lifeCycle: LifeCycle::alive(),
            transfer: Transfer::none(),
            auxiliaryContact: null,
            status: AnimalStatus::ACTIVE,
            ownerships: [
                new Ownership(
                    clientId: 'client-123',
                    role: OwnershipRole::PRIMARY,
                    status: OwnershipStatus::ACTIVE,
                    startedAt: $now,
                    endedAt: null
                ),
                new Ownership(
                    clientId: 'client-123', // DUPLICATE!
                    role: OwnershipRole::SECONDARY,
                    status: OwnershipStatus::ACTIVE,
                    startedAt: $now,
                    endedAt: null
                ),
            ],
            createdAt: $now,
            updatedAt: $now
        );

        $this->expectException(\App\Animal\Domain\Exception\PrimaryOwnerConflictException::class);

        $animal->ensureInvariants();
    }

    public function testResolveOwnershipPromotionDeterminism(): void
    {
        // Create animal with 2 secondaries started at different times
        $now     = new \DateTimeImmutable('2024-01-01T10:00:00+00:00');
        $earlier = new \DateTimeImmutable('2024-01-01T09:00:00+00:00');
        $later   = new \DateTimeImmutable('2024-01-01T11:00:00+00:00');

        $animal = Animal::reconstituteFromPersistence(
            id: AnimalId::fromString('01234567-89ab-cdef-0123-456789abcdef'),
            clinicId: ClinicId::fromString('12345678-9abc-def0-1234-56789abcdef0'),
            name: 'Rex',
            species: Species::DOG,
            sex: Sex::MALE,
            reproductiveStatus: ReproductiveStatus::INTACT,
            isMixedBreed: false,
            breedName: null,
            birthDate: null,
            color: null,
            photoUrl: null,
            identification: Identification::createEmpty(),
            lifeCycle: LifeCycle::alive(),
            transfer: Transfer::none(),
            auxiliaryContact: null,
            status: AnimalStatus::ACTIVE,
            ownerships: [
                new Ownership(
                    clientId: 'client-primary',
                    role: OwnershipRole::PRIMARY,
                    status: OwnershipStatus::ACTIVE,
                    startedAt: $earlier,
                    endedAt: null
                ),
                new Ownership(
                    clientId: 'client-late',
                    role: OwnershipRole::SECONDARY,
                    status: OwnershipStatus::ACTIVE,
                    startedAt: $later, // Started later
                    endedAt: null
                ),
                new Ownership(
                    clientId: 'client-early',
                    role: OwnershipRole::SECONDARY,
                    status: OwnershipStatus::ACTIVE,
                    startedAt: $earlier, // Started earlier
                    endedAt: null
                ),
            ],
            createdAt: $earlier,
            updatedAt: $now
        );

        // Archive primary owner
        $animal->resolveOwnershipForArchivedClient('client-primary', $now);

        // The EARLIEST secondary (client-early) should be promoted
        $activeOwnerships = array_filter($animal->ownerships(), static fn ($o) => $o->isActive());
        $primary          = array_values(array_filter($activeOwnerships, static fn ($o) => $o->isPrimary()))[0];

        self::assertSame('client-early', $primary->clientId);
    }

    public function testResolveOwnershipPromotionDeterminismWithSameStartDate(): void
    {
        // Test the clientId fallback when startedAt is the same
        $now = new \DateTimeImmutable('2024-01-01T10:00:00+00:00');

        $animal = Animal::reconstituteFromPersistence(
            id: AnimalId::fromString('01234567-89ab-cdef-0123-456789abcdef'),
            clinicId: ClinicId::fromString('12345678-9abc-def0-1234-56789abcdef0'),
            name: 'Rex',
            species: Species::DOG,
            sex: Sex::MALE,
            reproductiveStatus: ReproductiveStatus::INTACT,
            isMixedBreed: false,
            breedName: null,
            birthDate: null,
            color: null,
            photoUrl: null,
            identification: Identification::createEmpty(),
            lifeCycle: LifeCycle::alive(),
            transfer: Transfer::none(),
            auxiliaryContact: null,
            status: AnimalStatus::ACTIVE,
            ownerships: [
                new Ownership(
                    clientId: 'client-primary',
                    role: OwnershipRole::PRIMARY,
                    status: OwnershipStatus::ACTIVE,
                    startedAt: $now,
                    endedAt: null
                ),
                new Ownership(
                    clientId: 'client-zzz', // Alphabetically last
                    role: OwnershipRole::SECONDARY,
                    status: OwnershipStatus::ACTIVE,
                    startedAt: $now, // Same time
                    endedAt: null
                ),
                new Ownership(
                    clientId: 'client-aaa', // Alphabetically first
                    role: OwnershipRole::SECONDARY,
                    status: OwnershipStatus::ACTIVE,
                    startedAt: $now, // Same time
                    endedAt: null
                ),
            ],
            createdAt: $now,
            updatedAt: $now
        );

        // Archive primary owner
        $animal->resolveOwnershipForArchivedClient('client-primary', $now);

        // client-aaa should be promoted (alphabetically first as fallback)
        $activeOwnerships = array_filter($animal->ownerships(), static fn ($o) => $o->isActive());
        $primary          = array_values(array_filter($activeOwnerships, static fn ($o) => $o->isPrimary()))[0];

        self::assertSame('client-aaa', $primary->clientId);
    }

    private function createMinimalAnimal(): Animal
    {
        return Animal::create(
            id: AnimalId::fromString('01234567-89ab-cdef-0123-456789abcdef'),
            clinicId: ClinicId::fromString('12345678-9abc-def0-1234-56789abcdef0'),
            name: 'Rex',
            species: Species::DOG,
            sex: Sex::MALE,
            reproductiveStatus: ReproductiveStatus::INTACT,
            isMixedBreed: false,
            breedName: null,
            birthDate: null,
            color: null,
            photoUrl: null,
            identification: Identification::createEmpty(),
            lifeCycle: LifeCycle::alive(),
            transfer: Transfer::none(),
            auxiliaryContact: null,
            primaryOwnerClientId: 'client-123',
            secondaryOwnerClientIds: [],
            now: new \DateTimeImmutable('2024-01-01T10:00:00+00:00')
        );
    }
}
