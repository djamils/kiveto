<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Client\Application\Verification;

use App\Context\Client\Domain\Repository\ClientRepositoryInterface;
use App\Context\Client\Domain\TaxProfile\CompanyCheck;
use App\Context\Client\Domain\TaxProfile\CompanyCheckOutcome;
use App\Context\Client\Domain\TaxProfile\TaxProfileVerification;
use App\Context\Client\Domain\TaxProfile\VatCheck;
use App\Context\Client\Domain\TaxProfile\VatCheckOutcome;
use App\Context\Client\Domain\ValueObject\ClientId;
use App\Context\Client\Domain\ValueObject\ClientTaxStatus;
use App\Context\Client\Domain\ValueObject\ClinicId;
use App\Fixtures\Context\Client\Factory\ClientEntityFactory;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

/**
 * A sheet saved between the load and the save of a recorded verification
 * fails that save instead of being overwritten.
 */
final class RecordTaxProfileVerificationOptimisticLockTest extends KernelTestCase
{
    use Factories;

    private const string CLINIC_ID = '01960000-0000-7000-8000-00000000f1c1';
    private const string CLIENT_ID = '01960000-0000-7000-8000-00000000f1d1';

    public function testAConcurrentCommitFailsTheSave(): void
    {
        ClientEntityFactory::new()->withId(self::CLIENT_ID)->withClinicId(self::CLINIC_ID)->withName('Claire', 'Rostand')
            ->withTaxProfile(ClientTaxStatus::FRENCH_BUSINESS, 'Élevage des Calanques', '523456788', 'FR31523456788')
            ->active()->since('2026-01-01')->create()
        ;

        $repository = self::getContainer()->get(ClientRepositoryInterface::class);
        \assert($repository instanceof ClientRepositoryInterface);
        $connection = self::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);

        $client = $repository->get(ClinicId::fromString(self::CLINIC_ID), ClientId::fromString(self::CLIENT_ID));

        $connection->executeStatement(
            'UPDATE client__clients SET version = version + 1 WHERE id = UNHEX(REPLACE(:id, \'-\', \'\'))',
            ['id' => self::CLIENT_ID],
        );

        $at = new \DateTimeImmutable('2026-10-19 10:00:00');
        $client->recordTaxProfileVerification('523456788', 'FR31523456788', TaxProfileVerification::of(
            CompanyCheck::checked(CompanyCheckOutcome::ACTIVE, 'Élevage des Calanques', $at),
            VatCheck::checked(VatCheckOutcome::VALID, $at),
        ));

        $this->expectException(OptimisticLockException::class);

        $repository->save($client);
    }
}
