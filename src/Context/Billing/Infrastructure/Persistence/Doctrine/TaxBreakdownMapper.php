<?php

declare(strict_types=1);

namespace App\Context\Billing\Infrastructure\Persistence\Doctrine;

use App\Context\Billing\Domain\ValueObject\RateBucket;
use App\Shared\Domain\ValueObject\CurrencyCode;

/**
 * The frozen per-rate breakdown of a document, as stored in its
 * `tax_breakdown` JSON column.
 */
final readonly class TaxBreakdownMapper
{
    /**
     * @param list<RateBucket> $buckets
     *
     * @return list<array{rate: string, lineTotal: int, allowance: int, vat: int}>
     */
    public function toArray(array $buckets): array
    {
        return array_map(
            static fn (RateBucket $bucket): array => [
                'rate'      => $bucket->ratePercent(),
                'lineTotal' => $bucket->lineTotal()->minorUnits(),
                'allowance' => $bucket->allowance()->minorUnits(),
                'vat'       => $bucket->vat()->minorUnits(),
            ],
            $buckets,
        );
    }

    /**
     * @param list<array{rate: string, lineTotal: int, allowance: int, vat: int}> $rows
     *
     * @return list<RateBucket>
     */
    public function toDomain(array $rows, CurrencyCode $currency): array
    {
        return array_map(
            static fn (array $row): RateBucket => RateBucket::of(
                $row['rate'],
                $row['lineTotal'],
                $row['allowance'],
                $row['vat'],
                $currency,
            ),
            $rows,
        );
    }
}
