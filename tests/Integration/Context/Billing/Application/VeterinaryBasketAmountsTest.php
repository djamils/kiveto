<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Billing\Application;

use App\Context\Billing\Application\Service\InvoiceLineFactory;
use App\Context\Billing\Domain\Invoice;
use App\Context\Billing\Domain\Service\InvoiceAmountCalculator;
use App\Context\Billing\Domain\ValueObject\CalculationMode;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Context\Billing\Domain\ValueObject\InvoiceId;
use App\Context\Billing\Domain\ValueObject\InvoiceLine;
use App\Context\Billing\Domain\ValueObject\InvoiceLineSource;
use App\Context\Billing\Domain\ValueObject\OperationCategory;
use App\Context\Billing\Domain\ValueObject\Payer;
use App\Context\Billing\Domain\ValueObject\PriceMode;
use App\Fixtures\Context\Clinic\Factory\ClinicEntityFactory;
use App\Fixtures\System\Taxation\Story\TaxonomyBootstrapStory;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\CurrencyDecimals;
use App\Shared\Money\Domain\ValueObject\Money;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;

/**
 * AC 2: a typical veterinary basket whose VAT rates are resolved by the
 * Taxation BC for the clinic — at least two different rates.
 */
final class VeterinaryBasketAmountsTest extends KernelTestCase
{
    use Factories;

    private const string CLINIC_ID = '01960000-0000-7000-8000-0000000b5a01';

    /** label, category, unit price incl. VAT, quantity */
    private const array BASKET = [
        ['Consultation', 'veterinary.act.consultation', 4500, '1'],
        ['Vermifuge', 'veterinary.drug.companion', 1290, '1'],
        ['Antiparasitaire', 'veterinary.drug.companion', 735, '1'],
        ['Croquettes 10 kg', 'veterinary.food.therapeutic', 6499, '1'],
    ];

    public function testTheBasketIsChargedExactlyItsDisplayedPricesToAPrivateClient(): void
    {
        $invoice = $this->draft();

        self::assertCount(2, $invoice->amounts()->buckets());
        self::assertSame(4500 + 1290 + 735 + 6499, $invoice->totalGross()->minorUnits());

        $lineNets = 0;
        foreach ($invoice->lines() as $line) {
            $lineNets += $line->netAmount()->minorUnits();
        }

        $bases = 0;
        foreach ($invoice->amounts()->buckets() as $bucket) {
            $bases += $bucket->base()->minorUnits();
            self::assertSame($bucket->gross()->minorUnits(), $bucket->base()->minorUnits() + $bucket->vat()->minorUnits());
        }

        self::assertSame($bases, $lineNets);
        self::assertSame(OperationCategory::SERVICES, $invoice->lines()[0]->operationCategory());
        self::assertSame(OperationCategory::GOODS, $invoice->lines()[3]->operationCategory());
    }

    public function testTheBasketHoldsTheEn16931RulesWhenCalculatedExcludingTaxFirst(): void
    {
        $invoice = $this->draft();
        $amounts = new InvoiceAmountCalculator()->calculate(
            CalculationMode::EXCLUDING_TAX_FIRST,
            array_map(static fn (InvoiceLine $line) => $line->calculationLine(), $invoice->lines()),
            null,
            CurrencyCode::fromString('EUR'),
            CurrencyDecimals::of(2),
        );

        self::assertCount(2, $amounts->buckets());

        $lineNets = 0;
        foreach ($amounts->lines() as $i => $line) {
            /** @var numeric-string $quantity */
            $quantity = self::BASKET[$i][3];
            /** @var numeric-string $unitNet */
            $unitNet = $line->unitNet()->toDecimalString();

            // R120: line net = quantity × unit net, rounded.
            self::assertSame((int) bcmul(bcround(bcmul($quantity, $unitNet, 8), 2, \RoundingMode::HalfEven), '100', 0), $line->net()->minorUnits());
            $lineNets += $line->net()->minorUnits();
        }

        // BR-CO-10 and BR-CO-13.
        self::assertSame($lineNets, $amounts->totalLineNet()->minorUnits());
        self::assertSame($amounts->totalLineNet()->minorUnits() - $amounts->totalAllowance()->minorUnits(), $amounts->taxBasis()->minorUnits());

        // BR-CO-17: VAT per rate = base × rate, rounded.
        foreach ($amounts->buckets() as $bucket) {
            /** @var numeric-string $rate */
            $rate = $bucket->ratePercent();
            self::assertSame(
                (int) bcround(bcdiv(bcmul((string) $bucket->base()->minorUnits(), $rate, 6), '100', 6), 0, \RoundingMode::HalfEven),
                $bucket->vat()->minorUnits(),
            );
        }
    }

    private function draft(): Invoice
    {
        TaxonomyBootstrapStory::load();

        ClinicEntityFactory::createOne([
            'id'               => Uuid::fromString(self::CLINIC_ID),
            'slug'             => 'clinique-basket',
            'countryCode'      => 'FR',
            'jurisdictionCode' => null,
        ]);

        $factory = self::getContainer()->get(InvoiceLineFactory::class);
        \assert($factory instanceof InvoiceLineFactory);

        $now     = new \DateTimeImmutable('2026-10-21 10:00:00');
        $eur     = CurrencyCode::fromString('EUR');
        $invoice = Invoice::draft(
            InvoiceId::fromString('01960000-0000-7000-8000-0000000b5a02'),
            ClinicId::fromString(self::CLINIC_ID),
            null,
            Payer::anonymous(),
            $eur,
            $now,
        );

        foreach (self::BASKET as [$label, $category, $price, $quantity]) {
            $invoice->addLine($factory->line(
                clinicId: self::CLINIC_ID,
                label: $label,
                code: null,
                quantity: $quantity,
                enteredUnitPrice: Money::fromMinorUnits($price, $eur),
                priceMode: PriceMode::INCLUDING_TAX,
                taxCategoryCode: $category,
                source: InvoiceLineSource::MANUAL,
            ), $now);
        }

        return $invoice;
    }
}
