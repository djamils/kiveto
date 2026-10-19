<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Client\Application\Verification;

use App\Context\Client\Application\Command\CreateClient\ClientTaxProfileDto;
use App\Context\Client\Application\Command\CreateClient\ContactMethodDto;
use App\Context\Client\Application\Command\CreateClient\CreateClient;
use App\Context\Client\Application\Command\RequestTaxProfileVerification\RequestTaxProfileVerification;
use App\Context\Client\Application\Command\UpdateClientTaxProfile\UpdateClientTaxProfile;
use App\Context\Client\Domain\TaxProfile\VerificationRequestOutcome;
use App\Context\Client\Domain\ValueObject\ClientTaxStatus;
use App\Fixtures\Context\Client\Factory\ClientEntityFactory;
use App\Shared\Application\Bus\CommandBusInterface;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

/**
 * A verification is queued for the external worker in the business
 * transaction, once per change of identifiers or « Revérifier ».
 */
final class VerificationOutboxTest extends KernelTestCase
{
    use Factories;

    private const string CLINIC_ID = '01960000-0000-7000-8000-00000000e1c1';
    private const string CLIENT_ID = '01960000-0000-7000-8000-00000000e1d1';

    public function testANewProfessionalIsQueuedOnTheInteractiveQueue(): void
    {
        $clientId = $this->create(new ClientTaxProfileDto('french_business', 'Élevage des Calanques', '523456788', 'FR31523456788'));

        self::assertSame(1, $this->queued($clientId, 'external'));
        self::assertSame(0, $this->queued($clientId, 'external_batch'));
        self::assertSame(0, $this->queued($clientId, 'default'));
    }

    public function testAnIndividualIsNotQueued(): void
    {
        $clientId = $this->create(new ClientTaxProfileDto('individual', 'Refuge des Collines'));

        self::assertSame(0, $this->queued($clientId, 'external'));
    }

    public function testALegalNameChangeQueuesNothingAndASirenChangeQueuesOne(): void
    {
        $clientId = $this->create(new ClientTaxProfileDto('french_business', 'Élevage des Calanques', '523456788', null));

        $this->bus()->dispatch(new UpdateClientTaxProfile(self::CLINIC_ID, $clientId, new ClientTaxProfileDto('french_business', 'EARL Élevage des Calanques', '523456788', null)));
        self::assertSame(1, $this->queued($clientId, 'external'));

        $this->bus()->dispatch(new UpdateClientTaxProfile(self::CLINIC_ID, $clientId, new ClientTaxProfileDto('french_business', 'EARL Élevage des Calanques', '812345676', null)));
        self::assertSame(2, $this->queued($clientId, 'external'));
    }

    public function testReverifyingTwiceWithinADayQueuesOnce(): void
    {
        ClientEntityFactory::new()->withId(self::CLIENT_ID)->withClinicId(self::CLINIC_ID)->withName('Hugo', 'Marchal')
            ->withTaxProfile(ClientTaxStatus::FRENCH_BUSINESS, 'Centre équestre du Lac', '812345676')
            ->active()->since('2026-01-01')->create()
        ;

        self::assertSame(VerificationRequestOutcome::QUEUED, $this->bus()->dispatch(new RequestTaxProfileVerification(self::CLINIC_ID, self::CLIENT_ID)));
        self::assertSame(VerificationRequestOutcome::ALREADY_PENDING, $this->bus()->dispatch(new RequestTaxProfileVerification(self::CLINIC_ID, self::CLIENT_ID)));

        self::assertSame(1, $this->queued(self::CLIENT_ID, 'external'));
    }

    private function create(ClientTaxProfileDto $profile): string
    {
        $clientId = $this->bus()->dispatch(new CreateClient(
            clinicId: self::CLINIC_ID,
            firstName: 'Claire',
            lastName: 'Rostand',
            contactMethods: [new ContactMethodDto('phone', 'work', '+33491000000', true)],
            taxProfile: $profile,
        ));
        \assert(\is_string($clientId));

        return $clientId;
    }

    private function bus(): CommandBusInterface
    {
        $bus = self::getContainer()->get(CommandBusInterface::class);
        \assert($bus instanceof CommandBusInterface);

        return $bus;
    }

    private function queued(string $clientId, string $queue): int
    {
        $connection = self::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);

        $count = $connection->fetchOne(
            'SELECT COUNT(*) FROM shared__messenger_messages WHERE queue_name = :queue AND body LIKE :command AND body LIKE :client',
            ['queue' => $queue, 'command' => '%VerifyClientTaxProfile%', 'client' => '%' . $clientId . '%'],
        );

        return (int) (\is_scalar($count) ? $count : 0);
    }
}
