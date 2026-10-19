<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation\Clinic\ViewModel\Directory;

use App\Context\Client\Domain\TaxProfile\CompanyCheckOutcome;
use App\Context\Client\Domain\TaxProfile\VatCheckOutcome;
use App\Context\Client\Domain\ValueObject\ClientTaxStatus;
use App\Fixtures\Context\Client\Factory\ClientEntityFactory;
use App\Presentation\Clinic\ViewModel\Directory\ClientLookupReader;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

/**
 * The counter repeats only the warnings that matter when billing.
 */
final class ClientLookupReaderWarningTest extends KernelTestCase
{
    use Factories;

    private const string CLINIC_ID = '01960000-0000-7000-8000-0000000006c1';

    public function testOnlyTheBillingWarningsReachTheCard(): void
    {
        $ids = [
            'ceased'       => $this->pro('01960000-0000-7000-8000-0000000006d1', CompanyCheckOutcome::CEASED, VatCheckOutcome::INVALID, 'GAEC du Mont'),
            'vat'          => $this->pro('01960000-0000-7000-8000-0000000006d2', CompanyCheckOutcome::ACTIVE, VatCheckOutcome::INVALID, 'Haras du Mont Ventoux'),
            'confidential' => $this->pro('01960000-0000-7000-8000-0000000006d3', CompanyCheckOutcome::CONFIDENTIAL, VatCheckOutcome::VALID, null),
            'mismatch'     => $this->pro('01960000-0000-7000-8000-0000000006d4', CompanyCheckOutcome::ACTIVE, VatCheckOutcome::VALID, 'Autre nom officiel'),
            'unchecked'    => $this->pro('01960000-0000-7000-8000-0000000006d5', CompanyCheckOutcome::UNCHECKED, VatCheckOutcome::UNCHECKED, null),
        ];

        $reader = self::getContainer()->get(ClientLookupReader::class);
        \assert($reader instanceof ClientLookupReader);

        $warnings = array_column($reader->byIds(self::CLINIC_ID, array_values($ids)), 'warn', 'id');

        self::assertSame('Entreprise radiée selon le registre', $warnings[$ids['ceased']]);
        self::assertSame('N° de TVA non reconnu par VIES', $warnings[$ids['vat']]);
        self::assertNull($warnings[$ids['confidential']]);
        self::assertNull($warnings[$ids['mismatch']]);
        self::assertNull($warnings[$ids['unchecked']]);
    }

    private function pro(string $id, CompanyCheckOutcome $company, VatCheckOutcome $vat, ?string $registryName): string
    {
        $at = CompanyCheckOutcome::UNCHECKED === $company ? null : '2026-10-18 08:00:00';

        ClientEntityFactory::new()->withId($id)->withClinicId(self::CLINIC_ID)->withName('Pro', substr($id, -2))
            ->withTaxProfile(ClientTaxStatus::FRENCH_BUSINESS, 'Haras du Mont Ventoux', '392107454', 'FR76392107454')
            ->withTaxVerification($company, $registryName, $at, $vat, $at)
            ->active()->since('2026-01-01')->create()
        ;

        return $id;
    }
}
