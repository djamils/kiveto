<?php

declare(strict_types=1);

namespace App\Context\Regulatory\Domain\Service;

interface WorkingDayCalculatorInterface
{
    public function addWorkingDays(\DateTimeImmutable $from, int $days): \DateTimeImmutable;

    public function isWorkingDay(\DateTimeImmutable $date): bool;
}
