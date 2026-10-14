<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Animal\Domain;

use App\Context\Animal\Domain\Event\PreventiveCareRecorded;
use App\Context\Animal\Domain\PreventiveCareRecord;
use App\Context\Animal\Domain\ValueObject\AnimalId;
use App\Context\Animal\Domain\ValueObject\ClinicId;
use App\Context\Animal\Domain\ValueObject\PreventiveCareKind;
use App\Context\Animal\Domain\ValueObject\PreventiveCareRecordId;
use PHPUnit\Framework\TestCase;

final class PreventiveCareRecordTest extends TestCase
{
    private const string RECORD_ID = '0192c000-0000-7000-8000-000000000001';
    private const string ANIMAL_ID = '0192b000-0000-7000-8000-000000000001';
    private const string CLINIC_ID = '12345678-9abc-def0-1234-56789abcdef0';

    public function testRecordBuildsTheActAndRecordsItsEvent(): void
    {
        $recordedAt = new \DateTimeImmutable('2026-03-02 11:00:00');

        $record = $this->record(
            ' CHPPiL ',
            new \DateTimeImmutable('2026-03-02 09:00:00'),
            ' VX-77219 ',
            new \DateTimeImmutable('2027-03-02 15:00:00'),
            ' Dr Nguyen ',
            $recordedAt,
        );

        self::assertSame(self::RECORD_ID, $record->id()->toString());
        self::assertSame(self::CLINIC_ID, $record->clinicId()->toString());
        self::assertSame(self::ANIMAL_ID, $record->animalId()->toString());
        self::assertSame(PreventiveCareKind::VACCINATION, $record->kind());
        self::assertSame('CHPPiL', $record->label());
        self::assertSame('2026-03-02 00:00:00', $record->administeredOn()->format('Y-m-d H:i:s'));
        self::assertSame('VX-77219', $record->batchNumber());
        self::assertSame('2027-03-02 00:00:00', $record->nextDueOn()?->format('Y-m-d H:i:s'));
        self::assertSame('Dr Nguyen', $record->authorName());
        self::assertSame($recordedAt, $record->recordedAt());

        $events = $record->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(PreventiveCareRecorded::class, $events[0]);
        self::assertSame(self::RECORD_ID, $events[0]->aggregateId());
        self::assertSame([
            'preventiveCareRecordId' => self::RECORD_ID,
            'animalId'               => self::ANIMAL_ID,
            'clinicId'               => self::CLINIC_ID,
            'kind'                   => 'vaccination',
            'label'                  => 'CHPPiL',
            'administeredOn'         => '2026-03-02',
        ], $events[0]->payload());
    }

    public function testBatchNumberAndNextDueDateAreOptional(): void
    {
        $record = $this->record('Vermifugation', null, '  ', null);

        self::assertNull($record->batchNumber());
        self::assertNull($record->nextDueOn());
        self::assertNull($this->record('Vermifugation', null, null, null)->batchNumber());
    }

    public function testReconstituteDoesNotRecordEvents(): void
    {
        $record = PreventiveCareRecord::reconstitute(
            PreventiveCareRecordId::fromString(self::RECORD_ID),
            ClinicId::fromString(self::CLINIC_ID),
            AnimalId::fromString(self::ANIMAL_ID),
            PreventiveCareKind::OTHER,
            'Rappel',
            new \DateTimeImmutable('2030-01-01'),
            null,
            null,
            'Camille',
            new \DateTimeImmutable('2026-03-02'),
        );

        self::assertSame([], $record->pullDomainEvents());
    }

    public function testRejectsAnActDatedInTheFuture(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->record('CHPPiL', new \DateTimeImmutable('2026-03-04'), null, null, 'Camille', new \DateTimeImmutable('2026-03-02 11:00:00'));
    }

    public function testAcceptsTheNextCalendarDayForClinicsAheadOfUtc(): void
    {
        $record = $this->record('CHPPiL', new \DateTimeImmutable('2026-03-03'), null, null, 'Camille', new \DateTimeImmutable('2026-03-02 22:30:00'));

        self::assertSame('2026-03-03', $record->administeredOn()->format('Y-m-d'));
    }

    public function testRejectsAnEmptyLabel(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->record('  ');
    }

    public function testRejectsALabelThatIsTooLong(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->record(str_repeat('a', PreventiveCareRecord::MAX_LABEL_LENGTH + 1));
    }

    public function testRejectsABatchNumberThatIsTooLong(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->record('CHPPiL', null, str_repeat('a', PreventiveCareRecord::MAX_BATCH_LENGTH + 1));
    }

    public function testRejectsANextDueDateThatIsNotAfterTheAct(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->record('CHPPiL', new \DateTimeImmutable('2026-03-02'), null, new \DateTimeImmutable('2026-03-02'));
    }

    public function testRejectsAnActWithoutAuthor(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->record('CHPPiL', null, null, null, ' ');
    }

    public function testEveryKindHasALabel(): void
    {
        self::assertSame('Vaccination', PreventiveCareKind::VACCINATION->label());
        self::assertSame('Antiparasitaire interne', PreventiveCareKind::INTERNAL_PARASITE->label());
        self::assertSame('Antiparasitaire externe', PreventiveCareKind::EXTERNAL_PARASITE->label());
        self::assertSame('Autre acte de prévention', PreventiveCareKind::OTHER->label());
    }

    private function record(
        string $label,
        ?\DateTimeImmutable $administeredOn = null,
        ?string $batchNumber = null,
        ?\DateTimeImmutable $nextDueOn = null,
        string $author = 'Camille',
        ?\DateTimeImmutable $recordedAt = null,
    ): PreventiveCareRecord {
        return PreventiveCareRecord::record(
            PreventiveCareRecordId::fromString(self::RECORD_ID),
            ClinicId::fromString(self::CLINIC_ID),
            AnimalId::fromString(self::ANIMAL_ID),
            PreventiveCareKind::VACCINATION,
            $label,
            $administeredOn ?? new \DateTimeImmutable('2026-03-02'),
            $batchNumber,
            $nextDueOn,
            $author,
            $recordedAt ?? new \DateTimeImmutable('2026-03-02 11:00:00'),
        );
    }
}
