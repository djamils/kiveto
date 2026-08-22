<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Query\StockOverview;

/**
 * The states the stock page narrows on. Each one answers a question a clinic
 * actually asks: what is missing, what is about to be, and what can no longer be
 * used.
 */
enum StockStateFilter: string
{
    case OUT_OF_STOCK = 'OUT_OF_STOCK';
    case LOW          = 'LOW';
    case EXPIRING     = 'EXPIRING';
    case EXPIRED      = 'EXPIRED';
    case RECALLED     = 'RECALLED';

    public function label(): string
    {
        return match ($this) {
            self::OUT_OF_STOCK => 'En rupture',
            self::LOW          => 'Sous le seuil',
            self::EXPIRING     => 'Bientôt périmé',
            self::EXPIRED      => 'Périmé',
            self::RECALLED     => 'Lot rappelé',
        };
    }
}
