<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Domain\TaxProfile;

use App\Context\Client\Domain\TaxProfile\CompanyCheckOutcome;
use App\Context\Client\Domain\TaxProfile\TaxProfileWarning;
use App\Context\Client\Domain\TaxProfile\VatCheckOutcome;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TaxProfileWarning::class)]
#[CoversClass(CompanyCheckOutcome::class)]
#[CoversClass(VatCheckOutcome::class)]
final class TaxProfileWarningTest extends TestCase
{
    public function testEveryWarningHasItsFrenchLabel(): void
    {
        $labels = [];

        foreach (TaxProfileWarning::cases() as $warning) {
            $labels[$warning->value] = $warning->label();
        }

        self::assertSame([
            'unverified'           => 'Vérification en attente',
            'company_ceased'       => 'Entreprise radiée selon le registre',
            'company_not_found'    => 'SIREN introuvable dans le registre',
            'company_confidential' => 'Entreprise non diffusible au registre',
            'vat_invalid'          => 'N° de TVA non reconnu par VIES',
            'name_mismatch'        => 'Nom différent du registre',
        ], $labels);
    }

    public function testOnlyCeasedNotFoundAndInvalidVatAreShownAtTheCounter(): void
    {
        $shown = array_values(array_filter(TaxProfileWarning::cases(), static fn (TaxProfileWarning $w): bool => $w->shownAtCounter()));

        self::assertSame([TaxProfileWarning::COMPANY_CEASED, TaxProfileWarning::COMPANY_NOT_FOUND, TaxProfileWarning::VAT_INVALID], $shown);
    }

    public function testCompanyOutcomes(): void
    {
        $result = [];

        foreach (CompanyCheckOutcome::cases() as $outcome) {
            $result[$outcome->value] = [$outcome->isDefinite(), $outcome->label()];
        }

        self::assertSame([
            'unchecked'    => [false, 'non vérifiée'],
            'active'       => [true, 'active'],
            'ceased'       => [true, 'radiée'],
            'confidential' => [true, 'non diffusible'],
            'not_found'    => [true, 'introuvable'],
            'unavailable'  => [false, 'registre indisponible'],
        ], $result);
    }

    public function testVatOutcomes(): void
    {
        $result = [];

        foreach (VatCheckOutcome::cases() as $outcome) {
            $result[$outcome->value] = [$outcome->isDefinite(), $outcome->label()];
        }

        self::assertSame([
            'unchecked'      => [false, 'TVA non vérifiée'],
            'valid'          => [true, 'TVA valide'],
            'invalid'        => [true, 'TVA non reconnue'],
            'not_applicable' => [true, 'sans n° de TVA'],
            'unavailable'    => [false, 'VIES indisponible'],
        ], $result);
    }
}
