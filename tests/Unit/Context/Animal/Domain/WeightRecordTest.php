<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Animal\Domain;

use App\Context\Animal\Domain\Event\AnimalWeightRecorded;
use App\Context\Animal\Domain\ValueObject\AnimalId;
use App\Context\Animal\Domain\ValueObject\ClinicId;
use App\Context\Animal\Domain\ValueObject\WeightRecordId;
use App\Context\Animal\Domain\WeightRecord;
use PHPUnit\Framework\TestCase;

final class WeightRecordTest extends TestCase
{
    private const string RECORD_ID = '0192c000-0000-7000-8000-000000000001';
    private const string ANIMAL_ID = '0192b000-0000-7000-8000-000000000001';
    private const string CLINIC_ID = '12345678-9abc-def0-1234-56789abcdef0';

    public function testRecordBuildsTheWeighingAndRecordsItsEvent(): void
    {
        $recordedAt = new \DateTimeImmutable('2026-08-22 10:30:00');

        $record = $this->record(14_200, 6, new \DateTimeImmutable('2026-08-22 09:45:00'), ' Camille ', $recordedAt);

        self::assertSame(self::RECORD_ID, $record->id()->toString());
        self::assertSame(self::CLINIC_ID, $record->clinicId()->toString());
        self::assertSame(self::ANIMAL_ID, $record->animalId()->toString());
        self::assertSame(14_200, $record->weightGrams());
        self::assertSame(6, $record->bodyConditionScore());
        self::assertSame('2026-08-22 00:00:00', $record->measuredOn()->format('Y-m-d H:i:s'));
        self::assertSame('Camille', $record->authorName());
        self::assertSame($recordedAt, $record->recordedAt());

        $events = $record->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(AnimalWeightRecorded::class, $events[0]);
        self::assertSame(self::RECORD_ID, $events[0]->aggregateId());
        self::assertSame([
            'weightRecordId' => self::RECORD_ID,
            'animalId'       => self::ANIMAL_ID,
            'clinicId'       => self::CLINIC_ID,
            'weightGrams'    => 14_200,
            'measuredOn'     => '2026-08-22',
        ], $events[0]->payload());
    }

    public function testTheBodyConditionScoreIsOptional(): void
    {
        self::assertNull($this->record(4_800, null)->bodyConditionScore());
    }

    public function testReconstituteDoesNotRecordEvents(): void
    {
        $record = WeightRecord::reconstitute(
            WeightRecordId::fromString(self::RECORD_ID),
            ClinicId::fromString(self::CLINIC_ID),
            AnimalId::fromString(self::ANIMAL_ID),
            14_200,
            null,
            new \DateTimeImmutable('2030-01-01'),
            'Camille',
            new \DateTimeImmutable('2026-08-22'),
        );

        self::assertSame([], $record->pullDomainEvents());
    }

    public function testRejectsAWeighingDatedInTheFuture(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->record(14_200, null, new \DateTimeImmutable('2026-08-24'), 'Camille', new \DateTimeImmutable('2026-08-22 10:00:00'));
    }

    public function testAcceptsTheNextCalendarDayForClinicsAheadOfUtc(): void
    {
        $record = $this->record(14_200, null, new \DateTimeImmutable('2026-08-23'), 'Camille', new \DateTimeImmutable('2026-08-22 22:30:00'));

        self::assertSame('2026-08-23', $record->measuredOn()->format('Y-m-d'));
    }

    public function testRejectsAWeightOfZero(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->record(0, null);
    }

    public function testRejectsAWeightBeyondTheLimit(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->record(WeightRecord::MAX_WEIGHT_GRAMS + 1, null);
    }

    public function testRejectsABodyConditionScoreOutOfScale(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->record(14_200, 10);
    }

    public function testRejectsABodyConditionScoreBelowScale(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->record(14_200, 0);
    }

    public function testRejectsAWeighingWithoutAuthor(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->record(14_200, null, null, '  ');
    }

    private function record(
        int $weightGrams,
        ?int $score,
        ?\DateTimeImmutable $measuredOn = null,
        string $author = 'Camille',
        ?\DateTimeImmutable $recordedAt = null,
    ): WeightRecord {
        return WeightRecord::record(
            WeightRecordId::fromString(self::RECORD_ID),
            ClinicId::fromString(self::CLINIC_ID),
            AnimalId::fromString(self::ANIMAL_ID),
            $weightGrams,
            $score,
            $measuredOn ?? new \DateTimeImmutable('2026-08-22'),
            $author,
            $recordedAt ?? new \DateTimeImmutable('2026-08-22 10:30:00'),
        );
    }
}
