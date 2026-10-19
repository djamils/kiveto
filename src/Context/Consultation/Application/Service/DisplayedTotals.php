<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Service;

/**
 * The pre-tax and VAT figures a list or a visit shows for its billing lines.
 *
 * Display only: nothing here is stored or billed — Billing computes the real
 * amounts. Line prices keep the mode they were entered in, so the pre-tax
 * sums (`excl`) take their VAT on top while the all-taxes-included sums
 * (`incl`) are split into a pre-tax part and the VAT they already hold. A
 * category without a known rate shows its sum as is, without VAT.
 */
final class DisplayedTotals
{
    /**
     * @param array<string, int>       $htByTaxCategory  tax category => pre-tax minor units ('' = untaxed)
     * @param array<string, int>       $ttcByTaxCategory tax category => all-taxes-included minor units
     * @param \Closure(string): ?float $rateOf           VAT rate (percent) of a tax category, null when unknown
     *
     * @return array{ht: int, tva: int}
     */
    public static function of(array $htByTaxCategory, array $ttcByTaxCategory, \Closure $rateOf): array
    {
        $ht  = 0;
        $tva = 0;

        foreach ($htByTaxCategory as $category => $amount) {
            $ht += $amount;
            $rate = '' === $category ? null : $rateOf($category);

            if (null !== $rate) {
                $tva += (int) round($amount * $rate / 100);
            }
        }

        foreach ($ttcByTaxCategory as $category => $amount) {
            $rate = '' === $category ? null : $rateOf($category);
            $net  = null === $rate ? $amount : (int) round($amount * 100 / (100 + $rate));

            $ht += $net;
            $tva += $amount - $net;
        }

        return ['ht' => $ht, 'tva' => $tva];
    }
}
