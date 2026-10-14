<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Query\WatchStock;

use App\Shared\Application\Bus\QueryInterface;

/**
 * What needs someone's attention in a clinic's stock, today. The stock page
 * lists it; the navigation counts what is critical.
 */
final readonly class WatchStock implements QueryInterface
{
    /**
     * @param string $timeZone the clinic's: a lot expires at midnight where it is kept
     */
    public function __construct(
        public string $clinicId,
        public string $timeZone = 'Europe/Paris',
    ) {
    }
}
