<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Domain\Booking;

use App\Context\Scheduling\Domain\Booking\BookingMotif;
use App\Context\Scheduling\Domain\Booking\BookingMotifId;
use App\Context\Scheduling\Domain\Booking\BookingPreventiveCareKind;
use App\Context\Scheduling\Domain\Booking\ConfirmationMode;
use App\Context\Scheduling\Domain\Booking\InvalidBookingConfigurationException;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\PlanningBlockType;
use App\Context\Scheduling\Domain\ValueObject\UserId;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BookingMotifTest extends TestCase
{
    private const string ID     = '01960000-0000-7000-8000-00000000b001';
    private const string CLINIC = '01960000-0000-7000-8000-00000000c001';
    private const string VET_A  = '01960000-0000-7000-8000-00000000d001';
    private const string VET_B  = '01960000-0000-7000-8000-00000000d002';

    public function testCreateKeepsEveryField(): void
    {
        $motif = $this->motif(
            label: '  Consultation  ',
            description: ' Bilan annuel ',
            durationMin: 30,
            types: [PlanningBlockType::CONSULTATION, PlanningBlockType::HEALTH_CHECK],
            practitioners: [self::VET_A, self::VET_B],
            preventiveCareKind: BookingPreventiveCareKind::VACCINATION,
        );

        self::assertSame(self::ID, $motif->id()->toString());
        self::assertSame(self::CLINIC, $motif->clinicId()->toString());
        self::assertSame('Consultation', $motif->label());
        self::assertSame('Bilan annuel', $motif->description());
        self::assertSame(30, $motif->durationMin());
        self::assertTrue($motif->isOnlineEnabled());
        self::assertSame(ConfirmationMode::MANUAL, $motif->confirmationKnownClient());
        self::assertSame([PlanningBlockType::CONSULTATION, PlanningBlockType::HEALTH_CHECK], $motif->allowedBlockTypes());
        self::assertSame([self::VET_A, self::VET_B], array_map(static fn (UserId $id): string => $id->toString(), $motif->practitionerIds()));
        self::assertSame(BookingPreventiveCareKind::VACCINATION, $motif->preventiveCareKind());
        self::assertTrue($motif->newClientsAllowed());
        self::assertSame(3, $motif->sortOrder());
        self::assertNull($motif->archivedAt());
        self::assertFalse($motif->isArchived());
        self::assertTrue($motif->allowsBlockType(PlanningBlockType::HEALTH_CHECK));
        self::assertFalse($motif->allowsBlockType(PlanningBlockType::SURGERY));
        self::assertTrue($motif->isBookableWith(UserId::fromString(self::VET_B)));
        self::assertFalse($motif->isBookableWith(UserId::fromString('01960000-0000-7000-8000-00000000d009')));
    }

    public function testBlankDescriptionIsNull(): void
    {
        self::assertNull($this->motif(description: '   ')->description());
        self::assertNull($this->motif(description: null)->description());
    }

    public function testDuplicatesAreDropped(): void
    {
        $motif = $this->motif(
            types: [PlanningBlockType::CONSULTATION, PlanningBlockType::CONSULTATION],
            practitioners: [self::VET_A, self::VET_A],
        );

        self::assertSame([PlanningBlockType::CONSULTATION], $motif->allowedBlockTypes());
        self::assertCount(1, $motif->practitionerIds());
    }

    public function testBoundsAreAccepted(): void
    {
        self::assertSame(str_repeat('é', 80), $this->motif(label: str_repeat('é', 80))->label());
        self::assertSame(str_repeat('é', 500), $this->motif(description: str_repeat('é', 500))->description());
        self::assertSame(5, $this->motif(durationMin: 5)->durationMin());
        self::assertSame(240, $this->motif(durationMin: 240)->durationMin());
    }

    /**
     * @param list<PlanningBlockType> $types
     * @param list<string>            $practitioners
     */
    #[DataProvider('provideOutOfBoundsValuesAreRefusedWithAFrenchMessageCases')]
    public function testOutOfBoundsValuesAreRefusedWithAFrenchMessage(
        string $label,
        ?string $description,
        int $durationMin,
        array $types,
        array $practitioners,
        string $field,
        string $message,
    ): void {
        try {
            $this->motif($label, $description, $durationMin, $types, $practitioners);
            self::fail('The motif should be refused.');
        } catch (InvalidBookingConfigurationException $e) {
            self::assertSame($field, $e->field);
            self::assertSame($message, $e->getMessage());
        }
    }

    /**
     * @return iterable<string, array{string, ?string, int, list<PlanningBlockType>, list<string>, string, string}>
     */
    public static function provideOutOfBoundsValuesAreRefusedWithAFrenchMessageCases(): iterable
    {
        $ok   = [PlanningBlockType::CONSULTATION];
        $vets = [self::VET_A];

        yield 'blank label' => ['  ', null, 30, $ok, $vets, 'label', 'Le libellé du motif est obligatoire.'];
        yield 'label of 81' => [str_repeat('a', 81), null, 30, $ok, $vets, 'label', 'Le libellé du motif ne peut pas dépasser 80 caractères.'];
        yield 'description of 501' => ['Vaccin', str_repeat('a', 501), 30, $ok, $vets, 'description', 'La description ne peut pas dépasser 500 caractères.'];
        yield 'duration 0' => ['Vaccin', null, 0, $ok, $vets, 'durationMin', 'La durée doit être comprise entre 5 et 240 minutes.'];
        yield 'duration 4' => ['Vaccin', null, 4, $ok, $vets, 'durationMin', 'La durée doit être comprise entre 5 et 240 minutes.'];
        yield 'duration 245' => ['Vaccin', null, 245, $ok, $vets, 'durationMin', 'La durée doit être comprise entre 5 et 240 minutes.'];
        yield 'duration 32' => ['Vaccin', null, 32, $ok, $vets, 'durationMin', 'La durée doit être un multiple de 5 minutes.'];
        yield 'no block type' => ['Vaccin', null, 30, [], $vets, 'allowedBlockTypes', 'Choisissez au moins un type de plage.'];
        yield 'leave' => ['Vaccin', null, 30, [PlanningBlockType::CONSULTATION, PlanningBlockType::LEAVE], $vets, 'allowedBlockTypes', 'Ce type de plage n\'accepte pas de rendez-vous.'];
        yield 'break' => ['Vaccin', null, 30, [PlanningBlockType::BREAK], $vets, 'allowedBlockTypes', 'Ce type de plage n\'accepte pas de rendez-vous.'];
        yield 'no practitioner' => ['Vaccin', null, 30, $ok, [], 'practitionerIds', 'Choisissez au moins un praticien.'];
    }

    public function testUpdateReplacesTheFieldsAndKeepsIdentity(): void
    {
        $motif = $this->motif();

        $motif->update('Vaccin', null, 15, false, ConfirmationMode::AUTO, [PlanningBlockType::SURGERY], [UserId::fromString(self::VET_B)], null, false, 7);

        self::assertSame(self::ID, $motif->id()->toString());
        self::assertSame('Vaccin', $motif->label());
        self::assertNull($motif->description());
        self::assertSame(15, $motif->durationMin());
        self::assertFalse($motif->isOnlineEnabled());
        self::assertSame(ConfirmationMode::AUTO, $motif->confirmationKnownClient());
        self::assertSame([PlanningBlockType::SURGERY], $motif->allowedBlockTypes());
        self::assertTrue($motif->isBookableWith(UserId::fromString(self::VET_B)));
        self::assertFalse($motif->isBookableWith(UserId::fromString(self::VET_A)));
        self::assertNull($motif->preventiveCareKind());
        self::assertFalse($motif->newClientsAllowed());
        self::assertSame(7, $motif->sortOrder());
    }

    public function testArchiveKeepsTheFirstDate(): void
    {
        $motif = $this->motif();
        $first = new \DateTimeImmutable('2026-11-01 10:00:00');

        $motif->archive($first);
        $motif->archive(new \DateTimeImmutable('2026-11-02 10:00:00'));

        self::assertTrue($motif->isArchived());
        self::assertSame($first, $motif->archivedAt());
    }

    public function testReconstituteRestoresStateWithoutValidation(): void
    {
        $archivedAt = new \DateTimeImmutable('2026-10-01 08:00:00');
        $motif      = BookingMotif::reconstitute(
            BookingMotifId::fromString(self::ID),
            ClinicId::fromString(self::CLINIC),
            'Chirurgie',
            'Stérilisation',
            60,
            false,
            ConfirmationMode::AUTO,
            [PlanningBlockType::SURGERY],
            [UserId::fromString(self::VET_A)],
            BookingPreventiveCareKind::OTHER,
            false,
            2,
            $archivedAt,
        );

        self::assertSame('Chirurgie', $motif->label());
        self::assertSame('Stérilisation', $motif->description());
        self::assertSame(60, $motif->durationMin());
        self::assertSame(BookingPreventiveCareKind::OTHER, $motif->preventiveCareKind());
        self::assertSame($archivedAt, $motif->archivedAt());
        self::assertSame([], $motif->pullDomainEvents());
    }

    /**
     * @param list<PlanningBlockType>|null $types
     * @param list<string>|null            $practitioners
     */
    private function motif(
        string $label = 'Consultation',
        ?string $description = 'Bilan annuel',
        int $durationMin = 30,
        ?array $types = null,
        ?array $practitioners = null,
        ?BookingPreventiveCareKind $preventiveCareKind = null,
    ): BookingMotif {
        return BookingMotif::create(
            BookingMotifId::fromString(self::ID),
            ClinicId::fromString(self::CLINIC),
            $label,
            $description,
            $durationMin,
            true,
            ConfirmationMode::MANUAL,
            $types ?? [PlanningBlockType::CONSULTATION],
            array_map(UserId::fromString(...), $practitioners ?? [self::VET_A]),
            $preventiveCareKind,
            true,
            3,
        );
    }
}
