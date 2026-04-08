<?php

declare(strict_types=1);

namespace App\Tests\Unit\ClinicalCare\Domain\ValueObject;

use App\ClinicalCare\Domain\ValueObject\AnimalId;
use App\ClinicalCare\Domain\ValueObject\AppointmentId;
use App\ClinicalCare\Domain\ValueObject\ClinicId;
use App\ClinicalCare\Domain\ValueObject\ConsultationId;
use App\ClinicalCare\Domain\ValueObject\OwnerId;
use App\ClinicalCare\Domain\ValueObject\UserId;
use App\ClinicalCare\Domain\ValueObject\WaitingRoomEntryId;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Smoke-tests every UUID-backed value object in ClinicalCare. They all share
 * the same shape (private constructor + fromString + toString + equals), so
 * a single test per VO covers the round-trip + invalid-input + value-equality.
 */
final class IdValueObjectsTest extends TestCase
{
    private const string SAMPLE = '11111111-1111-4111-8111-111111111111';
    private const string OTHER  = '22222222-2222-4222-8222-222222222222';

    public function testAnimalId(): void
    {
        $id = AnimalId::fromString(self::SAMPLE);
        self::assertSame(self::SAMPLE, $id->toString());
        self::assertTrue($id->equals(AnimalId::fromString(self::SAMPLE)));
        self::assertFalse($id->equals(AnimalId::fromString(self::OTHER)));

        $this->expectException(\InvalidArgumentException::class);
        AnimalId::fromString('not-a-uuid');
    }

    public function testAppointmentId(): void
    {
        $id = AppointmentId::fromString(self::SAMPLE);
        self::assertSame(self::SAMPLE, $id->toString());
        self::assertTrue($id->equals(AppointmentId::fromString(self::SAMPLE)));
        self::assertFalse($id->equals(AppointmentId::fromString(self::OTHER)));

        $this->expectException(\InvalidArgumentException::class);
        AppointmentId::fromString('not-a-uuid');
    }

    public function testClinicId(): void
    {
        $id = ClinicId::fromString(self::SAMPLE);
        self::assertSame(self::SAMPLE, $id->toString());
        self::assertTrue($id->equals(ClinicId::fromString(self::SAMPLE)));
        self::assertFalse($id->equals(ClinicId::fromString(self::OTHER)));

        $this->expectException(\InvalidArgumentException::class);
        ClinicId::fromString('not-a-uuid');
    }

    public function testConsultationId(): void
    {
        $id = ConsultationId::fromString(self::SAMPLE);
        self::assertSame(self::SAMPLE, $id->toString());
        self::assertTrue($id->equals(ConsultationId::fromString(self::SAMPLE)));
        self::assertFalse($id->equals(ConsultationId::fromString(self::OTHER)));

        $this->expectException(\InvalidArgumentException::class);
        ConsultationId::fromString('not-a-uuid');
    }

    public function testConsultationIdGenerateProducesValidUuid(): void
    {
        $id = ConsultationId::generate();

        self::assertTrue(Uuid::isValid($id->toString()));
    }

    public function testOwnerId(): void
    {
        $id = OwnerId::fromString(self::SAMPLE);
        self::assertSame(self::SAMPLE, $id->toString());
        self::assertTrue($id->equals(OwnerId::fromString(self::SAMPLE)));
        self::assertFalse($id->equals(OwnerId::fromString(self::OTHER)));

        $this->expectException(\InvalidArgumentException::class);
        OwnerId::fromString('not-a-uuid');
    }

    public function testUserId(): void
    {
        $id = UserId::fromString(self::SAMPLE);
        self::assertSame(self::SAMPLE, $id->toString());
        self::assertTrue($id->equals(UserId::fromString(self::SAMPLE)));
        self::assertFalse($id->equals(UserId::fromString(self::OTHER)));

        $this->expectException(\InvalidArgumentException::class);
        UserId::fromString('not-a-uuid');
    }

    public function testWaitingRoomEntryId(): void
    {
        $id = WaitingRoomEntryId::fromString(self::SAMPLE);
        self::assertSame(self::SAMPLE, $id->toString());
        self::assertTrue($id->equals(WaitingRoomEntryId::fromString(self::SAMPLE)));
        self::assertFalse($id->equals(WaitingRoomEntryId::fromString(self::OTHER)));

        $this->expectException(\InvalidArgumentException::class);
        WaitingRoomEntryId::fromString('not-a-uuid');
    }
}
