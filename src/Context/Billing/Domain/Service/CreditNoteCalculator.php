<?php

declare(strict_types=1);

namespace App\Context\Billing\Domain\Service;

use App\Context\Billing\Domain\CreditNote;
use App\Context\Billing\Domain\Invoice;
use App\Context\Billing\Domain\ValueObject\CalculationMode;
use App\Context\Billing\Domain\ValueObject\InvoiceLine;
use App\Context\Billing\Domain\ValueObject\InvoiceLineId;
use App\Context\Billing\Domain\ValueObject\InvoiceLineSource;
use App\Context\Billing\Domain\ValueObject\RateBucket;
use App\Shared\Money\Domain\Allocation\LargestRemainderAllocator;

/**
 * Works out what a credit note of some lines of an issued invoice credits,
 * from the invoice's frozen amounts and nothing else.
 *
 * The credited lines keep their frozen nets. Per VAT rate, the note takes the
 * share of the invoice's discount that its lines weigh (largest remainder)
 * and its VAT: base × rate for a professional invoice (calculated tax
 * excluded first), the matching share of what the client paid for a private
 * one (tax included first). The note that credits the last lines of a rate
 * takes exactly what the previous notes left, so that the notes of an invoice
 * add up to the invoice per rate — to the cent, with integers only.
 */
final class CreditNoteCalculator
{
    /**
     * @param list<InvoiceLine>         $selected the invoice lines to credit
     * @param list<CreditNote>          $previous the notes already issued against the invoice
     * @param \Closure(): InvoiceLineId $nextId   an identifier for each line of the note
     *
     * @return array{lines: list<InvoiceLine>, breakdown: list<RateBucket>}
     */
    public function credit(Invoice $invoice, array $selected, array $previous, \Closure $nextId): array
    {
        $credited = [];

        foreach ($previous as $note) {
            $credited = [...$credited, ...$note->creditedLineIds()];
        }

        $breakdown = [];

        foreach ($invoice->amounts()->buckets() as $bucket) {
            $rate         = self::key($bucket->ratePercent());
            $selectedHere = array_filter($selected, static fn (InvoiceLine $line): bool => self::key($line->taxRatePercent()) === $rate);

            if ([] === $selectedHere) {
                continue;
            }

            $breakdown[] = $this->creditBucket($invoice, $bucket, $selectedHere, $credited, $previous);
        }

        $lines = array_map(
            static fn (InvoiceLine $line): InvoiceLine => InvoiceLine::reconstitute(
                id: $nextId(),
                label: $line->label(),
                code: $line->code(),
                quantity: $line->quantity(),
                enteredUnitPrice: $line->enteredUnitPrice(),
                priceMode: $line->priceMode(),
                taxCategoryCode: $line->taxCategoryCode(),
                taxRatePercent: $line->taxRatePercent(),
                operationCategory: $line->operationCategory(),
                source: InvoiceLineSource::CREDIT,
                sourceReference: $line->id()->toString(),
                amounts: $line->amounts(),
            ),
            $selected,
        );

        return ['lines' => $lines, 'breakdown' => $breakdown];
    }

    /**
     * @param array<int, InvoiceLine> $selected
     * @param list<string>            $credited the invoice lines already credited
     * @param list<CreditNote>        $previous
     */
    private function creditBucket(Invoice $invoice, RateBucket $bucket, array $selected, array $credited, array $previous): RateBucket
    {
        $rate        = self::key($bucket->ratePercent());
        $linesOfRate = array_filter($invoice->lines(), static fn (InvoiceLine $line): bool => self::key($line->taxRatePercent()) === $rate);

        $creditedNets  = 0;
        $creditedGross = 0;
        $selectedIds   = [];
        foreach ($selected as $line) {
            $creditedNets += $line->netAmount()->minorUnits();
            $creditedGross += $line->grossAmount()->minorUnits();
            $selectedIds[] = $line->id()->toString();
        }

        $remaining = 0;
        foreach ($linesOfRate as $line) {
            $alreadyCredited = \in_array($line->id()->toString(), $credited, true);
            $selectedNow     = \in_array($line->id()->toString(), $selectedIds, true);

            if (!$alreadyCredited && !$selectedNow) {
                ++$remaining;
            }
        }

        [$previousAllowance, $previousVat, $previousGross] = $this->previouslyCredited($previous, $rate);

        $leftAllowance = $bucket->allowance()->minorUnits() - $previousAllowance;
        $leftVat       = $bucket->vat()->minorUnits() - $previousVat;
        $currency      = $bucket->lineTotal()->currency();

        // The last lines of the rate: the note takes exactly what is left.
        if (0 === $remaining) {
            return RateBucket::of($bucket->ratePercent(), $creditedNets, $leftAllowance, $leftVat, $currency);
        }

        $lineTotal = $bucket->lineTotal()->minorUnits();
        $allowance = min(
            $leftAllowance,
            LargestRemainderAllocator::allocate(
                $bucket->allowance()->minorUnits(),
                [max(0, $creditedNets), max(0, $lineTotal - $creditedNets)],
            )[0],
        );
        $base = $creditedNets - $allowance;

        if (CalculationMode::EXCLUDING_TAX_FIRST === $invoice->calculationMode()) {
            // A professional invoice: VAT on the credited base, as EN16931 computes it.
            $vat = (int) bcround(bcdiv(bcmul((string) $base, self::key($bucket->ratePercent()), 12), '100', 12), 0, \RoundingMode::HalfEven);

            return RateBucket::of($bucket->ratePercent(), $creditedNets, $allowance, min($vat, $leftVat), $currency);
        }

        $grossOfRate = 0;
        foreach ($linesOfRate as $line) {
            $grossOfRate += $line->grossAmount()->minorUnits();
        }

        // What the client paid for these lines is given back; the VAT is what
        // that amount holds beyond the credited base.
        $paid = min(
            $bucket->gross()->minorUnits() - $previousGross,
            LargestRemainderAllocator::allocate(
                $bucket->gross()->minorUnits(),
                [max(0, $creditedGross), max(0, $grossOfRate - $creditedGross)],
            )[0],
        );
        $vat = $paid - $base;

        return RateBucket::of($bucket->ratePercent(), $creditedNets, $allowance, min($vat, $leftVat), $currency);
    }

    /**
     * @param list<CreditNote> $previous
     *
     * @return array{int, int, int} allowance, VAT and gross already credited at the rate
     */
    private function previouslyCredited(array $previous, string $rate): array
    {
        $allowance = 0;
        $vat       = 0;
        $gross     = 0;

        foreach ($previous as $note) {
            foreach ($note->amounts()->buckets() as $bucket) {
                if (self::key($bucket->ratePercent()) === $rate) {
                    $allowance += $bucket->allowance()->minorUnits();
                    $vat += $bucket->vat()->minorUnits();
                    $gross += $bucket->gross()->minorUnits();
                }
            }
        }

        return [$allowance, $vat, $gross];
    }

    /**
     * @return numeric-string
     */
    private static function key(string $ratePercent): string
    {
        // Rates were validated as decimals when the lines and buckets were built.
        /** @var numeric-string $rate */
        $rate = $ratePercent;

        return bcadd($rate, '0', 4);
    }
}
