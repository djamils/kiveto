<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Command\ArchiveBookingMotif;

use App\Context\Scheduling\Domain\Booking\BookingMotifId;
use App\Context\Scheduling\Domain\Booking\BookingMotifNotFoundException;
use App\Context\Scheduling\Domain\Booking\BookingMotifRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ArchiveBookingMotifHandler
{
    public function __construct(
        private BookingMotifRepositoryInterface $motifs,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(ArchiveBookingMotif $command): void
    {
        $motif = $this->motifs->findInClinic(BookingMotifId::fromString($command->motifId), ClinicId::fromString($command->clinicId))
            ?? throw new BookingMotifNotFoundException($command->motifId);

        $motif->archive($this->clock->now());
        $this->motifs->save($motif);
    }
}
