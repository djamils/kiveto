<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Query\WatchStock;

enum StockWatchKind: string
{
    /** A lot past its date still holds something: it has to leave the shelf. */
    case EXPIRED_LOT = 'EXPIRED_LOT';

    /** Received before, nothing usable left. */
    case OUT_OF_STOCK = 'OUT_OF_STOCK';

    /** Still some, but no more than the threshold the clinic set. */
    case BELOW_THRESHOLD = 'BELOW_THRESHOLD';

    /** A usable lot that expires within the window. */
    case EXPIRING_LOT = 'EXPIRING_LOT';

    /** A lot withdrawn recently, kept in view for a while. */
    case RECALLED_LOT = 'RECALLED_LOT';
}
