<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Clinic\Application\Query;

use App\Context\Clinic\Application\Query\Clinic\GetClinicSellerIdentity\ClinicSellerIdentityDto;
use App\Context\Clinic\Application\Query\Clinic\GetClinicSellerIdentity\GetClinicSellerIdentity;
use App\Context\Clinic\Infrastructure\Persistence\Doctrine\Embeddable\LetterheadEmbeddable;
use App\Fixtures\Context\Clinic\Factory\ClinicEntityFactory;
use App\Shared\Application\Bus\QueryBusInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

/**
 * The seller the Billing context names on an invoice, read through the query bus.
 */
final class GetClinicSellerIdentityTest extends KernelTestCase
{
    use Factories;

    private QueryBusInterface $queryBus;

    protected function setUp(): void
    {
        parent::setUp();

        $queryBus = self::getContainer()->get(QueryBusInterface::class);
        \assert($queryBus instanceof QueryBusInterface);
        $this->queryBus = $queryBus;
    }

    public function testTheSellerIsTheCompanyOnTheLetterhead(): void
    {
        $id = ClinicEntityFactory::new()
            ->withName('Clinique Vétérinaire de Paris')
            ->withLetterhead(new LetterheadEmbeddable(
                legalName: 'SELARL Clinique Vétérinaire de Paris',
                legalForm: 'SELARL',
                shareCapital: '30 000 €',
                siret: '81245690300028',
                vatNumber: 'FR20812456903',
                rcsCity: 'Paris',
                rcsNumber: '812 456 903',
                streetLine1: '18 rue des Abbesses',
                streetLine2: 'Bâtiment A',
                postalCode: '75018',
                city: 'Paris',
                countryCode: 'FR',
            ))
            ->withBillingSettings([
                'invoiceNumberPrefix'          => 'PAR',
                'professionalPaymentTermsDays' => 45,
                'iban'                         => 'FR7630006000011234567890189',
                'bic'                          => 'AGRIFRPP',
            ])
            ->create()
            ->getId()
            ->toRfc4122()
        ;

        $seller = $this->queryBus->ask(new GetClinicSellerIdentity($id));

        self::assertInstanceOf(ClinicSellerIdentityDto::class, $seller);
        self::assertSame($id, $seller->clinicId);
        self::assertSame('SELARL Clinique Vétérinaire de Paris', $seller->legalName);
        self::assertSame('812456903', $seller->siren);
        self::assertSame('FR20812456903', $seller->vatNumber);
        self::assertSame(['18 rue des Abbesses', 'Bâtiment A'], $seller->streetLines);
        self::assertSame('75018', $seller->postalCode);
        self::assertSame('Paris', $seller->city);
        self::assertSame('FR', $seller->countryCode);
        self::assertSame('SELARL, au capital de 30 000 €, RCS Paris 812 456 903', $seller->legalSentence);
        self::assertSame(45, $seller->professionalPaymentTermsDays);
        self::assertSame('FR7630006000011234567890189', $seller->iban);
        self::assertSame('AGRIFRPP', $seller->bic);
        self::assertSame('PAR', $seller->invoicePrefix);
        self::assertSame('AV', $seller->creditNotePrefix);
        self::assertTrue($seller->numberingIsUnique);
    }

    public function testAClinicThatFilledInNothingIsNamedAfterItselfWithTheDefaultPrefixes(): void
    {
        $id = ClinicEntityFactory::new()
            ->withName('Clinique du Port')
            ->withBillingSettings([
                'billingAddress' => ['streetLine1' => '5 quai du Port', 'city' => 'Marseille', 'countryCode' => 'FR', 'postalCode' => '13002'],
            ])
            ->create(['countryCode' => 'FR'])
            ->getId()
            ->toRfc4122()
        ;

        $seller = $this->queryBus->ask(new GetClinicSellerIdentity($id));

        self::assertInstanceOf(ClinicSellerIdentityDto::class, $seller);
        self::assertSame('Clinique du Port', $seller->legalName);
        self::assertNull($seller->siren);
        self::assertNull($seller->vatNumber);
        // Invoices are issued from the billing address when there is one.
        self::assertSame(['5 quai du Port'], $seller->streetLines);
        self::assertSame('13002', $seller->postalCode);
        self::assertSame('Marseille', $seller->city);
        self::assertNull($seller->legalSentence);
        self::assertNull($seller->professionalPaymentTermsDays);
        self::assertNull($seller->iban);
        self::assertSame('FAC', $seller->invoicePrefix);
        self::assertSame('AV', $seller->creditNotePrefix);
        self::assertTrue($seller->numberingIsUnique);
    }

    public function testAnInvalidStoredIdentityIsReportedAsMissing(): void
    {
        $id = ClinicEntityFactory::new()
            ->withLetterhead(new LetterheadEmbeddable(siret: '81245690300028', vatNumber: 'FR41812456903'))
            ->create(['countryCode' => 'FR'])
            ->getId()
            ->toRfc4122()
        ;

        $seller = $this->queryBus->ask(new GetClinicSellerIdentity($id));

        self::assertInstanceOf(ClinicSellerIdentityDto::class, $seller);
        self::assertSame('812456903', $seller->siren);
        self::assertNull($seller->vatNumber);
        self::assertSame([], $seller->streetLines);
        self::assertNull($seller->postalCode);
        self::assertNull($seller->city);
        self::assertSame('FR', $seller->countryCode);
    }

    public function testTwoEstablishmentsOfOneCompanyOnTheSamePrefixesAreNotUniquelyNumbered(): void
    {
        // Older data, saved before the prefixes were checked: both left on « FAC ».
        ClinicEntityFactory::new()->withLetterhead(new LetterheadEmbeddable(siret: '90112233300015'))->create();
        $cassis = ClinicEntityFactory::new()
            ->withLetterhead(new LetterheadEmbeddable(siret: '90112233300023'))
            ->create()
            ->getId()
            ->toRfc4122()
        ;

        $seller = $this->queryBus->ask(new GetClinicSellerIdentity($cassis));

        self::assertInstanceOf(ClinicSellerIdentityDto::class, $seller);
        self::assertSame('901122333', $seller->siren);
        self::assertFalse($seller->numberingIsUnique);
    }

    public function testAnUnknownClinicHasNoSellerIdentity(): void
    {
        self::assertNull($this->queryBus->ask(new GetClinicSellerIdentity('01960000-0000-7000-8000-00000000dead')));
    }
}
