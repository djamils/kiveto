<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Port;

/**
 * The daily history of active veterinarians per clinic (D6): one row per
 * clinic and day holding the highest count seen that day.
 */
interface VeterinarianDailyCountStoreInterface
{
    /**
     * Keeps the higher of the stored count and this one for that day.
     *
     * @return int the count stored for the day after the write
     */
    public function record(string $clinicId, \DateTimeImmutable $day, int $count): int;
}
