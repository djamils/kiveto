<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation\Clinic\ViewModel\Client;

use App\Context\Client\Application\Port\ClientReadRepositoryInterface;
use App\Context\Client\Domain\TaxProfile\CompanyCheckOutcome;
use App\Context\Client\Domain\TaxProfile\VatCheckOutcome;
use App\Context\Client\Domain\ValueObject\ClientId;
use App\Context\Client\Domain\ValueObject\ClientTaxStatus;
use App\Context\Client\Domain\ValueObject\ClinicId;
use App\Fixtures\Context\Client\Factory\ClientEntityFactory;
use App\Presentation\Clinic\ViewModel\Client\ClientSheetBuilder;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

/**
 * The « Profil fiscal » card fed with the verification (busy_6 states).
 */
final class ClientSheetBuilderTaxProfileTest extends KernelTestCase
{
    use Factories;

    private const string CLINIC_ID = '01960000-0000-7000-8000-0000000005c1';
    private const string CLIENT_ID = '01960000-0000-7000-8000-0000000005d1';

    public function testAnUncheckedProfessional(): void
    {
        $fisc = $this->fisc($this->pro('Élevage des Calanques', 'FR31523456788'));

        self::assertIsString($fisc['annuaireUrl']);
        self::assertIsString($fisc['verifyUrl']);
        self::assertSame('/clients/company-registry/search', parse_url($fisc['annuaireUrl'], \PHP_URL_PATH));
        self::assertSame('/clients/' . self::CLIENT_ID . '/tax-profile/verify', parse_url($fisc['verifyUrl'], \PHP_URL_PATH));
        self::assertNull($fisc['vies']);
        self::assertNull($fisc['verif']);
        self::assertSame(['company' => 'unchecked', 'vat' => 'unchecked', 'registryName' => null, 'pending' => false, 'warnings' => [['code' => 'unverified', 'label' => 'Vérification en attente']]], $fisc['verification']);
    }

    public function testAPendingProfessional(): void
    {
        $fisc = $this->fisc($this->pro('Centre équestre du Lac', null)->withTaxVerification(CompanyCheckOutcome::UNCHECKED, null, null, VatCheckOutcome::UNCHECKED, null, '-1 hour'));

        self::assertIsArray($fisc['verification']);
        self::assertTrue($fisc['verification']['pending']);
    }

    public function testAVerifiedProfessional(): void
    {
        $fisc = $this->fisc($this->pro('Élevage des Calanques', 'FR31523456788')
            ->withTaxVerification(CompanyCheckOutcome::ACTIVE, 'Élevage des Calanques', '2026-11-12 07:12:00', VatCheckOutcome::VALID, '2026-11-12 07:12:00'));

        self::assertTrue($fisc['vies']);
        // Paris time.
        self::assertSame('12/11 à 08:12', $fisc['verif']);
        self::assertSame(['company' => 'active', 'vat' => 'valid', 'registryName' => 'Élevage des Calanques', 'pending' => false, 'warnings' => []], $fisc['verification']);
    }

    public function testACeasedCompanyWithAnInvalidVatNumber(): void
    {
        $fisc = $this->fisc($this->pro('GAEC du Mont', 'FR14441569738', '441569738')
            ->withTaxVerification(CompanyCheckOutcome::CEASED, 'GAEC du Mont', '2026-10-18 08:00:00', VatCheckOutcome::INVALID, '2026-10-18 08:00:00'));

        self::assertFalse($fisc['vies']);
        self::assertIsArray($fisc['verification']);
        self::assertSame([
            ['code' => 'company_ceased', 'label' => 'Entreprise radiée selon le registre'],
            ['code' => 'vat_invalid', 'label' => 'N° de TVA non reconnu par VIES'],
        ], $fisc['verification']['warnings']);
    }

    public function testANameMismatch(): void
    {
        $fisc = $this->fisc($this->pro('Élevage des Calanques — site d\'Aubagne', null)
            ->withTaxVerification(CompanyCheckOutcome::ACTIVE, 'Élevage des Calanques', '2026-10-18 08:00:00', VatCheckOutcome::NOT_APPLICABLE, '2026-10-18 08:00:00'));

        self::assertIsArray($fisc['verification']);
        self::assertSame('Élevage des Calanques', $fisc['verification']['registryName']);
        self::assertSame([['code' => 'name_mismatch', 'label' => 'Nom différent du registre']], $fisc['verification']['warnings']);
    }

    public function testAnIndividualHasNoVerification(): void
    {
        $fisc = $this->fisc(ClientEntityFactory::new()->withId(self::CLIENT_ID)->withClinicId(self::CLINIC_ID)->withName('Inès', 'Perrin')
            ->withTaxProfile(ClientTaxStatus::INDIVIDUAL, 'Refuge des Collines')->active()->since('2026-01-01'));

        self::assertNull($fisc['verifyUrl']);
        self::assertNull($fisc['verification']);
    }

    private function pro(string $legalName, ?string $vat, string $siren = '523456788'): ClientEntityFactory
    {
        return ClientEntityFactory::new()->withId(self::CLIENT_ID)->withClinicId(self::CLINIC_ID)->withName('Claire', 'Rostand')
            ->withTaxProfile(ClientTaxStatus::FRENCH_BUSINESS, $legalName, $siren, $vat)->active()->since('2026-01-01')
        ;
    }

    /**
     * @return array<mixed>
     */
    private function fisc(ClientEntityFactory $factory): array
    {
        $factory->create();

        $clients = self::getContainer()->get(ClientReadRepositoryInterface::class);
        \assert($clients instanceof ClientReadRepositoryInterface);
        $view = $clients->findById(ClinicId::fromString(self::CLINIC_ID), ClientId::fromString(self::CLIENT_ID));
        \assert(null !== $view);

        $builder = self::getContainer()->get(ClientSheetBuilder::class);
        \assert($builder instanceof ClientSheetBuilder);

        $fisc = $builder->build($view, new \DateTimeZone('Europe/Paris'), new \DateTimeImmutable('2026-11-12 10:00:00'))['fisc'];
        \assert(\is_array($fisc));

        return $fisc;
    }
}
