<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Service;

/** Where a preventive care item stands against its due date. */
enum CareItemStatus: string
{
    case OVERDUE    = 'overdue';
    case DUE_SOON   = 'due_soon';
    case UP_TO_DATE = 'up_to_date';
}
