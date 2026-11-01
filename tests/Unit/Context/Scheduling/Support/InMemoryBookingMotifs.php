<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Support;

use App\Context\Scheduling\Domain\Booking\BookingMotif;
use App\Context\Scheduling\Domain\Booking\BookingMotifId;
use App\Context\Scheduling\Domain\Booking\BookingMotifRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;

final class InMemoryBookingMotifs implements BookingMotifRepositoryInterface
{
    /** @var array<string, BookingMotif> */
    public array $motifs = [];

    public int $saves = 0;

    public function save(BookingMotif $motif): void
    {
        $this->motifs[$motif->id()->toString()] = $motif;
        ++$this->saves;
    }

    public function findInClinic(BookingMotifId $id, ClinicId $clinicId): ?BookingMotif
    {
        $motif = $this->motifs[$id->toString()] ?? null;

        return null !== $motif && $motif->clinicId()->equals($clinicId) ? $motif : null;
    }

    public function listForClinic(ClinicId $clinicId, bool $includeArchived): array
    {
        $motifs = array_values(array_filter(
            $this->motifs,
            static fn (BookingMotif $motif): bool => $motif->clinicId()->equals($clinicId) && ($includeArchived || !$motif->isArchived()),
        ));
        usort($motifs, static fn (BookingMotif $a, BookingMotif $b): int => [$a->sortOrder(), $a->label()] <=> [$b->sortOrder(), $b->label()]);

        return $motifs;
    }
}
