<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Application\Command\ArchiveBookingMotif;

use App\Context\Scheduling\Application\Command\ArchiveBookingMotif\ArchiveBookingMotif;
use App\Context\Scheduling\Application\Command\ArchiveBookingMotif\ArchiveBookingMotifHandler;
use App\Context\Scheduling\Domain\Booking\BookingMotif;
use App\Context\Scheduling\Domain\Booking\BookingMotifId;
use App\Context\Scheduling\Domain\Booking\BookingMotifNotFoundException;
use App\Context\Scheduling\Domain\Booking\ConfirmationMode;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\PlanningBlockType;
use App\Context\Scheduling\Domain\ValueObject\UserId;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Unit\Context\Scheduling\Support\InMemoryBookingMotifs;
use PHPUnit\Framework\TestCase;

final class ArchiveBookingMotifHandlerTest extends TestCase
{
    private const string ID     = '01960000-0000-7000-8000-00000000b001';
    private const string CLINIC = '01960000-0000-7000-8000-00000000c001';

    public function testArchivesTheMotifOfTheClinic(): void
    {
        $motifs = $this->motifs();
        $now    = new \DateTimeImmutable('2026-11-01 09:00:00');

        new ArchiveBookingMotifHandler($motifs, new FrozenClock($now))(new ArchiveBookingMotif(self::CLINIC, self::ID));

        self::assertSame($now, $motifs->motifs[self::ID]->archivedAt());
        self::assertSame(2, $motifs->saves);
    }

    public function testAMotifOfAnotherClinicIsNotFound(): void
    {
        $motifs = $this->motifs();

        $this->expectException(BookingMotifNotFoundException::class);

        new ArchiveBookingMotifHandler($motifs, new FrozenClock(new \DateTimeImmutable()))(
            new ArchiveBookingMotif('01960000-0000-7000-8000-00000000c002', self::ID),
        );
    }

    private function motifs(): InMemoryBookingMotifs
    {
        $motifs = new InMemoryBookingMotifs();
        $motifs->save(BookingMotif::create(
            BookingMotifId::fromString(self::ID),
            ClinicId::fromString(self::CLINIC),
            'Vaccin',
            null,
            15,
            true,
            ConfirmationMode::AUTO,
            [PlanningBlockType::CONSULTATION],
            [UserId::fromString('01960000-0000-7000-8000-00000000d001')],
            null,
            false,
            0,
        ));

        return $motifs;
    }
}
