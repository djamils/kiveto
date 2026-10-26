<?php

declare(strict_types=1);

namespace App\Shared\Money\Domain\Allocation;

use App\Shared\Money\Domain\Exception\AllocationException;

/**
 * Splits an integer amount into integer shares proportional to weights, the
 * shares adding up to the amount exactly (largest remainder method).
 *
 * Every share first gets the floor of its exact proportion; the units left
 * over go one by one to the shares whose dropped fraction was the largest,
 * the first index winning a tie. A negative amount is split as its absolute
 * value and the sign applied afterwards, so -10 over three equal weights
 * gives -4, -3, -3 like 10 gives 4, 3, 3. When every weight is zero the
 * amount is split equally.
 *
 * Integer arithmetic only (bcmath for the products, which may exceed what an
 * int holds).
 */
final class LargestRemainderAllocator
{
    /**
     * @param list<int> $weights
     *
     * @return list<int>
     */
    public static function allocate(int $total, array $weights): array
    {
        if ([] === $weights) {
            throw new AllocationException('weights array is empty');
        }

        foreach ($weights as $weight) {
            if ($weight < 0) {
                throw new AllocationException('weights must not be negative');
            }
        }

        $sum = '0';
        foreach ($weights as $weight) {
            $sum = bcadd($sum, (string) $weight, 0);
        }

        if ('0' === $sum) {
            $weights = array_fill(0, \count($weights), 1);
            $sum     = (string) \count($weights);
        }

        $sign     = $total < 0 ? -1 : 1;
        $absolute = (string) abs($total);

        $shares     = [];
        $remainders = [];
        $allocated  = 0;

        foreach ($weights as $index => $weight) {
            $product            = bcmul($absolute, (string) $weight, 0);
            $share              = (int) bcdiv($product, $sum, 0);
            $shares[$index]     = $share;
            $remainders[$index] = bcmod($product, $sum, 0);
            $allocated += $share;
        }

        $left = abs($total) - $allocated;

        // Stable on ties: among equal remainders the lower index comes first.
        $order = array_keys($remainders);
        usort(
            $order,
            static fn (int $a, int $b): int => bccomp($remainders[$b], $remainders[$a], 0) ?: $a <=> $b,
        );

        foreach (\array_slice($order, 0, $left) as $index) {
            ++$shares[$index];
        }

        return array_values(array_map(static fn (int $share): int => $sign * $share, $shares));
    }
}
