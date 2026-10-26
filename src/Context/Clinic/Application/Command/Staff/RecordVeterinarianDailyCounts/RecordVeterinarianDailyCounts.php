<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Command\Staff\RecordVeterinarianDailyCounts;

use App\Shared\Application\Bus\CommandInterface;

/**
 * Counts today's active veterinarians of a clinic (or of every clinic when
 * no id is given), keeps the day's highest count and hands it to
 * Notification for the SMS quota.
 */
final readonly class RecordVeterinarianDailyCounts implements CommandInterface
{
    public function __construct(public ?string $clinicId = null)
    {
    }
}
