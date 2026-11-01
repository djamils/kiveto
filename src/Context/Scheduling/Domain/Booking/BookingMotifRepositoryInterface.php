<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\Booking;

use App\Context\Scheduling\Domain\ValueObject\ClinicId;

interface BookingMotifRepositoryInterface
{
    public function save(BookingMotif $motif): void;

    /**
     * The motif of that clinic, null when unknown or of another clinic.
     */
    public function findInClinic(BookingMotifId $id, ClinicId $clinicId): ?BookingMotif;

    /**
     * Ordered by sort order, then label.
     *
     * @return list<BookingMotif>
     */
    public function listForClinic(ClinicId $clinicId, bool $includeArchived): array;
}
