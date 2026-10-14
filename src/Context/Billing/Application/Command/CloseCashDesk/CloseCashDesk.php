<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Command\CloseCashDesk;

use App\Shared\Application\Bus\CommandInterface;

/**
 * « Clôturer la journée »: what was counted in the till at the end of a
 * business day, from midnight to midnight in the clinic's time zone.
 */
final readonly class CloseCashDesk implements CommandInterface
{
    public function __construct(
        public string $clinicId,
        public string $businessDate,
        public \DateTimeImmutable $dayStart,
        public \DateTimeImmutable $dayEnd,
        public ?int $countedCashMinorUnits,
        public ?int $countedChequesMinorUnits,
        public ?string $comment = null,
        public ?string $closedByUserId = null,
    ) {
    }
}
