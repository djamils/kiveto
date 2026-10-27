<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Command\Staff\RecordVeterinarianDailyCounts;

use App\Shared\Application\Bus\CommandInterface;

/**
 * Counts today's active veterinarians of a clinic, keeps the day's highest
 * count and hands it to Notification for the SMS quota. Without a clinic id,
 * queues one such count per clinic that is not CLOSED (asynchronous jobs).
 */
final readonly class RecordVeterinarianDailyCounts implements CommandInterface
{
    public function __construct(public ?string $clinicId = null)
    {
    }
}
