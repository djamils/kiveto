<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Patient;

use App\Context\Patient\Domain\Event\PatientArchived;
use App\Context\Patient\Domain\Event\PatientCreated;
use App\Context\Patient\Domain\Event\PatientDisplayLabelChanged;
use App\Context\Patient\Domain\Event\PatientDisplayLabelCustomized;
use App\Context\Patient\Domain\Event\PatientLinkedToAnimal;
use App\Context\Patient\Domain\Event\PatientReconciledInto;
use App\Context\Patient\Domain\Exception\PatientAlreadyLinkedException;
use App\Context\Patient\Domain\Exception\PatientNotActiveException;
use App\Context\Patient\Domain\Patient;
use App\Context\Patient\Domain\ValueObject\AnimalLink;
use App\Context\Patient\Domain\ValueObject\ClinicId;
use App\Context\Patient\Domain\ValueObject\DisplayLabel;
use App\Context\Patient\Domain\ValueObject\DisplayLabelKind;
use App\Context\Patient\Domain\ValueObject\PatientId;
use App\Context\Patient\Domain\ValueObject\PatientStatus;
use PHPUnit\Framework\TestCase;

final class PatientTest extends TestCase
{
    public function testCreateUnidentifiedRecordsPatientCreatedEvent(): void
    {
        $id       = $this->makeId();
        $clinicId = $this->makeClinicId();
        $now      = $this->makeNow();

        $patient = Patient::createUnidentified(
            id: $id,
            clinicId: $clinicId,
            provisionalLabel: 'Chat roux',
            observedSpecies: 'cat',
            observedColor: 'roux',
            now: $now,
        );

        self::assertSame($id, $patient->id());
        self::assertSame($clinicId, $patient->clinicId());
        self::assertSame(PatientStatus::Active, $patient->status());
        self::assertSame(DisplayLabelKind::Provisional, $patient->displayLabel()->kind());
        self::assertSame('Chat roux', $patient->displayLabel()->value());
        self::assertNull($patient->animalLink());
        self::assertSame('cat', $patient->observedSpecies());
        self::assertSame('roux', $patient->observedColor());
        self::assertSame(1, $patient->version());

        $events = $patient->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(PatientCreated::class, $events[0]);

        $created = $events[0];
        self::assertInstanceOf(PatientCreated::class, $created);
        self::assertSame($id->toString(), $created->patientId);
        self::assertSame($clinicId->toString(), $created->clinicId);
        self::assertSame(DisplayLabelKind::Provisional->value, $created->displayLabelKind);
        self::assertNull($created->animalId);
    }

    public function testCreateFromAnimalRecordsPatientCreatedEvent(): void
    {
        $id         = $this->makeId();
        $clinicId   = $this->makeClinicId();
        $now        = $this->makeNow();
        $animalLink = AnimalLink::fromAnimalId('aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa');

        $patient = Patient::createFromAnimal(
            id: $id,
            clinicId: $clinicId,
            animalLink: $animalLink,
            animalName: 'Rex',
            now: $now,
        );

        self::assertSame(PatientStatus::Active, $patient->status());
        self::assertSame(DisplayLabelKind::FromAnimal, $patient->displayLabel()->kind());
        self::assertSame('Rex', $patient->displayLabel()->value());
        self::assertNotNull($patient->animalLink());
        self::assertSame('aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa', $patient->animalLink()->animalId());

        $events = $patient->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(PatientCreated::class, $events[0]);

        $created = $events[0];
        self::assertInstanceOf(PatientCreated::class, $created);
        self::assertSame(DisplayLabelKind::FromAnimal->value, $created->displayLabelKind);
        self::assertSame('aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa', $created->animalId);
    }

    public function testLinkToAnimalThrowsIfAlreadyLinked(): void
    {
        $now        = $this->makeNow();
        $animalLink = AnimalLink::fromAnimalId('aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa');

        $patient = Patient::createFromAnimal(
            id: $this->makeId(),
            clinicId: $this->makeClinicId(),
            animalLink: $animalLink,
            animalName: 'Rex',
            now: $now,
        );

        $this->expectException(PatientAlreadyLinkedException::class);

        $patient->linkToAnimal(AnimalLink::fromAnimalId('bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb'), $now);
    }

    public function testLinkToAnimalThrowsIfNotActive(): void
    {
        $now     = $this->makeNow();
        $patient = Patient::createUnidentified(
            id: $this->makeId(),
            clinicId: $this->makeClinicId(),
            provisionalLabel: 'Test',
            observedSpecies: null,
            observedColor: null,
            now: $now,
        );

        $patient->archive($now);

        $this->expectException(PatientNotActiveException::class);

        $patient->linkToAnimal(AnimalLink::fromAnimalId('aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa'), $now);
    }

    public function testLinkToAnimalEmitsEvent(): void
    {
        $now     = $this->makeNow();
        $patient = Patient::createUnidentified(
            id: $this->makeId(),
            clinicId: $this->makeClinicId(),
            provisionalLabel: 'Test',
            observedSpecies: null,
            observedColor: null,
            now: $now,
        );

        $_ = $patient->pullDomainEvents(); // Clear PatientCreated

        $patient->linkToAnimal(AnimalLink::fromAnimalId('aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa'), $now);

        $events = $patient->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(PatientLinkedToAnimal::class, $events[0]);
    }

    public function testUpdateDisplayLabelIsNoOpForCustomKind(): void
    {
        $now     = $this->makeNow();
        $patient = Patient::createUnidentified(
            id: $this->makeId(),
            clinicId: $this->makeClinicId(),
            provisionalLabel: 'Initial',
            observedSpecies: null,
            observedColor: null,
            now: $now,
        );

        $patient->customizeDisplayLabel('My Custom Label', $now);
        $_ = $patient->pullDomainEvents(); // Clear all events so far

        // This should be a no-op since kind is Custom
        $patient->updateDisplayLabel(DisplayLabel::fromAnimal('Rex'), $now);

        $events = $patient->pullDomainEvents();
        self::assertCount(0, $events);
        self::assertSame(DisplayLabelKind::Custom, $patient->displayLabel()->kind());
        self::assertSame('My Custom Label', $patient->displayLabel()->value());
    }

    public function testCustomizeDisplayLabelSetsCustomKind(): void
    {
        $now     = $this->makeNow();
        $patient = Patient::createUnidentified(
            id: $this->makeId(),
            clinicId: $this->makeClinicId(),
            provisionalLabel: 'Initial',
            observedSpecies: null,
            observedColor: null,
            now: $now,
        );

        $_ = $patient->pullDomainEvents(); // Clear PatientCreated

        $patient->customizeDisplayLabel('My Custom Label', $now);

        self::assertSame(DisplayLabelKind::Custom, $patient->displayLabel()->kind());
        self::assertSame('My Custom Label', $patient->displayLabel()->value());

        $events = $patient->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(PatientDisplayLabelCustomized::class, $events[0]);
    }

    public function testUpdateDisplayLabelEmitsEventWhenChanged(): void
    {
        $now     = $this->makeNow();
        $patient = Patient::createUnidentified(
            id: $this->makeId(),
            clinicId: $this->makeClinicId(),
            provisionalLabel: 'Initial',
            observedSpecies: null,
            observedColor: null,
            now: $now,
        );

        $_ = $patient->pullDomainEvents(); // Clear PatientCreated

        $patient->updateDisplayLabel(DisplayLabel::fromAnimal('Rex'), $now);

        $events = $patient->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(PatientDisplayLabelChanged::class, $events[0]);
    }

    public function testReconcileIntoSetsArchivedStatus(): void
    {
        $now     = $this->makeNow();
        $patient = Patient::createUnidentified(
            id: $this->makeId(),
            clinicId: $this->makeClinicId(),
            provisionalLabel: 'Test',
            observedSpecies: null,
            observedColor: null,
            now: $now,
        );

        $_ = $patient->pullDomainEvents(); // Clear PatientCreated

        $targetId = 'ffffffff-ffff-ffff-ffff-ffffffffffff';
        $patient->reconcileInto($targetId, $now);

        self::assertSame(PatientStatus::Archived, $patient->status());

        $events = $patient->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(PatientReconciledInto::class, $events[0]);

        $event = $events[0];
        self::assertInstanceOf(PatientReconciledInto::class, $event);
        self::assertSame($targetId, $event->targetPatientId);
    }

    public function testArchiveThrowsIfNotActive(): void
    {
        $now     = $this->makeNow();
        $patient = Patient::createUnidentified(
            id: $this->makeId(),
            clinicId: $this->makeClinicId(),
            provisionalLabel: 'Test',
            observedSpecies: null,
            observedColor: null,
            now: $now,
        );

        $patient->archive($now);

        $this->expectException(PatientNotActiveException::class);

        $patient->archive($now);
    }

    public function testArchiveEmitsEvent(): void
    {
        $now     = $this->makeNow();
        $patient = Patient::createUnidentified(
            id: $this->makeId(),
            clinicId: $this->makeClinicId(),
            provisionalLabel: 'Test',
            observedSpecies: null,
            observedColor: null,
            now: $now,
        );

        $_ = $patient->pullDomainEvents(); // Clear PatientCreated

        $patient->archive($now);

        self::assertSame(PatientStatus::Archived, $patient->status());

        $events = $patient->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(PatientArchived::class, $events[0]);
    }

    public function testReconstituteFromPersistenceDoesNotRecordEvents(): void
    {
        $id         = $this->makeId();
        $clinicId   = $this->makeClinicId();
        $now        = $this->makeNow();
        $animalLink = AnimalLink::fromAnimalId('aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa');

        $patient = Patient::reconstituteFromPersistence(
            id: $id,
            clinicId: $clinicId,
            status: PatientStatus::Active,
            displayLabel: DisplayLabel::fromAnimal('Rex'),
            animalLink: $animalLink,
            observedSpecies: null,
            observedColor: null,
            version: 3,
            createdAt: $now,
            updatedAt: $now,
        );

        self::assertSame($id, $patient->id());
        self::assertSame($clinicId, $patient->clinicId());
        self::assertSame(PatientStatus::Active, $patient->status());
        self::assertSame(3, $patient->version());
        self::assertCount(0, $patient->pullDomainEvents());
    }

    private function makeId(): PatientId
    {
        return PatientId::fromString('01234567-89ab-cdef-0123-456789abcdef');
    }

    private function makeClinicId(): ClinicId
    {
        return ClinicId::fromString('12345678-9abc-def0-1234-56789abcdef0');
    }

    private function makeNow(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2024-01-01T10:00:00+00:00');
    }
}
