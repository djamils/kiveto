<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation;

use App\Context\Billing\Application\Command\RecordCounterSale\CounterSaleNewClient;
use App\Context\Billing\Application\Command\RecordCounterSale\RecordCounterSale;
use App\Context\Client\Application\Command\CreateClient\ClientTaxProfileDto;
use App\Context\Client\Application\Command\CreateClient\CreateClient;
use App\Fixtures\Context\Catalog\Factory\ArticleEntityFactory;
use App\Presentation\Clinic\ViewModel\Client\TaxProfileInput;
use App\Presentation\Clinic\ViewModel\Directory\DirectoryRegistrar;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Tests\Support\RecordingCommandBus;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

/**
 * The directory « Nouveau client » window and the counter « Professionnel »
 * tab share one tax-profile block: the same input ends in the same
 * CreateClient, carrying the same tax profile.
 */
final class ProfessionalClientEntryPointsTest extends KernelTestCase
{
    use Factories;

    private const string CLINIC_ID = '01960000-0000-7000-8000-0000000002c1';

    /** The shared block, as both windows send it. */
    private const array BLOCK = ['fisc' => 'pro', 'rs' => 'Élevage des Calanques', 'siren' => '523 456 788 00012', 'tva' => 'fr 31 523456788'];

    public function testBothEntryPointsDispatchTheSameCreateClient(): void
    {
        $bus = self::getContainer()->get(RecordingCommandBus::class);
        \assert($bus instanceof RecordingCommandBus);

        $registrar = self::getContainer()->get(DirectoryRegistrar::class);
        \assert($registrar instanceof DirectoryRegistrar);

        $result = $registrar->createClient(self::CLINIC_ID, [...self::BLOCK, 'civ' => 'Mme', 'nom' => 'ROSTAND', 'pre' => 'Claire', 'fixe' => '04 91 22 33 44']);
        self::assertTrue($result['ok'], $result['message']);
        self::assertSame('Client créé — Élevage des Calanques', $result['message']);

        $fromDirectory = $bus->recorded(CreateClient::class);
        $bus->reset();

        $article  = ArticleEntityFactory::new()->forClinic(self::CLINIC_ID)->create(['basePriceMinorUnits' => 1500]);
        $tax      = TaxProfileInput::fromArray(self::BLOCK);
        $commands = self::getContainer()->get(CommandBusInterface::class);
        \assert(null !== $tax && $commands instanceof CommandBusInterface);

        $commands->dispatch(new RecordCounterSale(
            clinicId: self::CLINIC_ID,
            payerClientId: null,
            lines: [['articleId' => $article->getId()->toRfc4122(), 'quantity' => 1]],
            method: 'CARD',
            newClient: new CounterSaleNewClient(CounterSaleNewClient::MADAM, 'Rostand', 'Claire', '0491223355', taxProfile: $tax->toBillingInput()),
        ));

        $fromCounter = $bus->recorded(CreateClient::class);

        self::assertCount(1, $fromDirectory);
        self::assertCount(1, $fromCounter);
        self::assertSame(
            $this->profile($fromDirectory[0]->taxProfile),
            $this->profile($fromCounter[0]->taxProfile),
        );
        self::assertSame(['french_business', 'Élevage des Calanques', '523456788', 'FR31523456788'], $this->profile($fromCounter[0]->taxProfile));

        $connection = self::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);

        $rows = $connection->fetchAllAssociative(
            'SELECT tax_status, legal_name, siren, vat_number FROM client__clients WHERE clinic_id = UUID_TO_BIN(:clinic)',
            ['clinic' => self::CLINIC_ID],
        );

        self::assertCount(2, $rows);
        self::assertSame($rows[0], $rows[1]);
    }

    /**
     * @return list<?string>
     */
    private function profile(?ClientTaxProfileDto $dto): array
    {
        self::assertNotNull($dto);

        return [$dto->status, $dto->legalName, $dto->siren, $dto->vatNumber];
    }
}
