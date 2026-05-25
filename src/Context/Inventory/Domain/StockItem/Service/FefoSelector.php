<?php

declare(strict_types=1);

namespace App\Context\Inventory\Domain\StockItem\Service;

use App\Context\Inventory\Domain\StockItem\Entity\Lot;
use App\Context\Inventory\Domain\StockItem\ValueObject\LotStatus;

/**
 * FEFO (First Expired, First Out) lot selector.
 * Orders ACTIVE lots by expiry date ascending, then by received-at ascending (tie-break).
 * Non-ACTIVE lots (EXPIRED, RECALLED, DEPLETED) are excluded.
 */
final class FefoSelector
{
    /**
     * @param list<Lot> $lots
     *
     * @return list<Lot>
     */
    public function selectOrderedForConsumption(array $lots): array
    {
        $active = array_filter($lots, static fn (Lot $lot): bool => LotStatus::ACTIVE === $lot->status());

        $sorted = array_values($active);

        usort($sorted, static function (Lot $a, Lot $b): int {
            // Primary sort: expiryDate ASC
            $expiryDiff = $a->expiryDate()->getTimestamp() <=> $b->expiryDate()->getTimestamp();

            if (0 !== $expiryDiff) {
                return $expiryDiff;
            }

            // Secondary sort: receivedAt ASC (null goes last)
            $aReceived = $a->receivedAt();
            $bReceived = $b->receivedAt();

            if (null === $aReceived && null === $bReceived) {
                return 0;
            }

            if (null === $aReceived) {
                return 1;
            }

            if (null === $bReceived) {
                return -1;
            }

            return $aReceived->getTimestamp() <=> $bReceived->getTimestamp();
        });

        return $sorted;
    }
}
