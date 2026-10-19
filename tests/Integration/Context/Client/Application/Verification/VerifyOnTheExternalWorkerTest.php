<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Client\Application\Verification;

use App\Context\Client\Application\Command\VerifyClientTaxProfile\VerifyClientTaxProfile;
use App\Context\Client\Application\Port\CompanyRecord;
use App\Context\Client\Application\Port\CompanyRegistryInterface;
use App\Context\Client\Application\Port\VatNumberVerifierInterface;
use App\Context\Client\Application\Port\VatVerificationResult;
use App\Context\Client\Domain\Repository\ClientRepositoryInterface;
use App\Context\Client\Domain\TaxProfile\CompanyCheckOutcome;
use App\Context\Client\Domain\TaxProfile\VatCheckOutcome;
use App\Context\Client\Domain\ValueObject\ClientId;
use App\Context\Client\Domain\ValueObject\ClientTaxStatus;
use App\Context\Client\Domain\ValueObject\ClinicId;
use App\Fixtures\Context\Client\Factory\ClientEntityFactory;
use App\Shared\Application\Bus\ExternalApiCommandBusInterface;
use App\Shared\Application\ExternalApi\ExternalServiceUnavailable;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\EventListener\StopWorkerOnMessageLimitListener;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Worker;
use Zenstruck\Foundry\Test\Factories;

/**
 * AC 18: the external worker calls the services with no database transaction
 * open, keeps each answer independent, then records them.
 */
final class VerifyOnTheExternalWorkerTest extends KernelTestCase
{
    use Factories;

    private const string CLINIC_ID = '01960000-0000-7000-8000-00000000a1c1';
    private const string CLIENT_ID = '01960000-0000-7000-8000-00000000a1d1';

    /** @var list<bool> */
    private array $transactionActive = [];

    public function testARegistryOutageKeepsTheViesAnswerAndNoTransactionIsOpenDuringTheCalls(): void
    {
        ClientEntityFactory::new()->withId(self::CLIENT_ID)->withClinicId(self::CLINIC_ID)->withName('Claire', 'Rostand')
            ->withTaxProfile(ClientTaxStatus::FRENCH_BUSINESS, 'Élevage des Calanques', '523456788', 'FR31523456788')
            ->active()->since('2026-01-01')->create()
        ;

        $container  = self::getContainer();
        $connection = $container->get(Connection::class);
        \assert($connection instanceof Connection);
        $spy = function () use ($connection): void {
            $this->transactionActive[] = $connection->isTransactionActive();
        };

        $container->set(CompanyRegistryInterface::class, new class($spy) implements CompanyRegistryInterface {
            public function __construct(private readonly \Closure $spy)
            {
            }

            public function search(string $query, int $limit): array
            {
                return [];
            }

            public function findBySiren(string $siren): ?CompanyRecord
            {
                ($this->spy)();

                throw ExternalServiceUnavailable::failed('recherche-entreprises', 503);
            }
        });
        $container->set(VatNumberVerifierInterface::class, new class($spy) implements VatNumberVerifierInterface {
            public function __construct(private readonly \Closure $spy)
            {
            }

            public function verify(string $vatNumber): VatVerificationResult
            {
                ($this->spy)();

                return new VatVerificationResult(true);
            }
        });

        $bus = $container->get(ExternalApiCommandBusInterface::class);
        \assert($bus instanceof ExternalApiCommandBusInterface);
        $bus->send(new VerifyClientTaxProfile(self::CLINIC_ID, self::CLIENT_ID));

        $this->consumeOneExternalMessage();

        self::assertSame([false, false], $this->transactionActive);

        $em = $container->get(EntityManagerInterface::class);
        \assert($em instanceof EntityManagerInterface);
        $em->clear();

        $repository = $container->get(ClientRepositoryInterface::class);
        \assert($repository instanceof ClientRepositoryInterface);
        $verification = $repository->get(ClinicId::fromString(self::CLINIC_ID), ClientId::fromString(self::CLIENT_ID))->taxProfileVerification();

        self::assertSame(CompanyCheckOutcome::UNAVAILABLE, $verification->company()->outcome());
        self::assertSame(VatCheckOutcome::VALID, $verification->vat()->outcome());
        self::assertNull($verification->requestedAt());
    }

    private function consumeOneExternalMessage(): void
    {
        $container = self::getContainer();
        $receiver  = $container->get('messenger.transport.async_external');
        \assert($receiver instanceof ReceiverInterface);
        $bus = $container->get('messenger.routable_message_bus');
        \assert($bus instanceof MessageBusInterface);

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(1));

        new Worker(['async_external' => $receiver], $bus, $dispatcher)->run(['sleep' => 0]);
    }
}
