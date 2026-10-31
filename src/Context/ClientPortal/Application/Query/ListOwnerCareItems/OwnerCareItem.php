<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\ListOwnerCareItems;

use App\Context\ClientPortal\Application\Service\CareItemStatus;

/** The latest act of a preventive protocol that has a next due date. */
final readonly class OwnerCareItem
{
    /**
     * @param \DateTimeImmutable $lastDoneOn a date, no time of day
     * @param \DateTimeImmutable $dueOn      a date, no time of day
     */
    public function __construct(
        public string $kind,
        public string $label,
        public \DateTimeImmutable $lastDoneOn,
        public \DateTimeImmutable $dueOn,
        public CareItemStatus $status,
    ) {
    }
}
