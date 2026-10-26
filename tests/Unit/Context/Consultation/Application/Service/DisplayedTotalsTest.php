<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Consultation\Application\Service;

use App\Context\Consultation\Application\Service\DisplayedTotals;
use PHPUnit\Framework\TestCase;

final class DisplayedTotalsTest extends TestCase
{
    public function testPreTaxSumsTakeTheirVatOnTopAndTtcSumsAreSplit(): void
    {
        $rates = ['veterinary.act.consultation' => 20.0, 'veterinary.drug.companion' => 5.5];

        $totals = DisplayedTotals::of(
            ['veterinary.act.consultation' => 1000, '' => 300],
            ['veterinary.act.consultation' => 6000, 'veterinary.drug.companion' => 1990],
            static fn (string $category): ?float => $rates[$category] ?? null,
        );

        // HT: 1000 + 300 + 5000 + round(1990 / 1.055) = 1886 → 8186.
        // VAT: 200 + 1000 + (1990 − 1886) = 104 → 1304.
        self::assertSame(['ht' => 8186, 'tva' => 1304], $totals);
    }

    public function testAnAllTaxesIncludedSumWithoutKnownRateIsShownAsIs(): void
    {
        $totals = DisplayedTotals::of(
            [],
            ['' => 4500, 'unknown.category' => 1200],
            static fn (string $category): ?float => null,
        );

        self::assertSame(['ht' => 5700, 'tva' => 0], $totals);
    }
}
