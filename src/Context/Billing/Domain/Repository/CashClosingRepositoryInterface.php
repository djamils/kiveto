<?php

declare(strict_types=1);

namespace App\Context\Billing\Domain\Repository;

use App\Context\Billing\Domain\CashClosing;
use App\Context\Billing\Domain\ValueObject\ClinicId;

interface CashClosingRepositoryInterface
{
    public function save(CashClosing $closing): void;

    /**
     * The closing of one business day (Y-m-d), if the day is closed.
     */
    public function findForDate(ClinicId $clinicId, string $businessDate): ?CashClosing;

    /**
     * The number the clinic's next closing takes: closings run in one
     * sequence, from the first day the clinic closed its till.
     */
    public function nextNumber(ClinicId $clinicId): int;
}
