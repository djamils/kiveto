<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Application\Command\CreateClient;

use App\Context\Client\Application\Command\CreateClient\ContactMethodDto;
use App\Context\Client\Application\Command\CreateClient\CreateClient;
use App\Context\Client\Application\Command\CreateClient\CreateClientHandler;
use App\Context\Client\Application\Command\CreateClient\NewAnimalDto;
use App\Context\Client\Application\Command\UpdateClientPostalAddress\PostalAddressDto;
use App\Context\Client\Application\Port\ClientAnimalRegistryInterface;
use App\Context\Client\Domain\Client;
use App\Context\Client\Domain\Repository\ClientRepositoryInterface;
use App\Context\Client\Domain\ValueObject\Civility;
use App\Context\Client\Domain\ValueObject\ConsentKind;
use App\Context\Client\Domain\ValueObject\ContactChannel;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Event\DomainEventInterface;
use App\Shared\Domain\Time\ClockInterface;
use PHPUnit\Framework\TestCase;

final class CreateClientHandlerTest extends TestCase
{
    public function testHandleCreatesClientAndReturnsId(): void
    {
        $now            = new \DateTimeImmutable('2024-01-01 10:00:00');
        $contactMethods = [
            new ContactMethodDto('email', 'work', 'john@example.com', true),
        ];

        $command = new CreateClient(
            clinicId: '12345678-9abc-def0-1234-56789abcdef0',
            firstName: 'John',
            lastName: 'Doe',
            contactMethods: $contactMethods
        );

        $clientRepository = $this->createMock(ClientRepositoryInterface::class);
        $eventBus         = $this->createMock(EventBusInterface::class);
        $clock            = $this->createMock(ClockInterface::class);

        $clock->expects(self::once())
            ->method('now')
            ->willReturn($now)
        ;

        $clientRepository->expects(self::once())
            ->method('save')
            ->with(self::callback(function (Client $client): bool {
                return 'John' === $client->identity()->firstName
                    && 'Doe' === $client->identity()->lastName;
            }))
        ;

        $eventBus->expects(self::once())
            ->method('publish')
            ->with([], self::isInstanceOf(DomainEventInterface::class))
        ;

        $handler = new CreateClientHandler($clientRepository, new DomainEventPublisher($eventBus), $clock, $this->createStub(ClientAnimalRegistryInterface::class));
        $result  = $handler($command);

        self::assertNotEmpty($result);
    }

    public function testHandleCreatesClientWithPhoneContactMethod(): void
    {
        $now            = new \DateTimeImmutable('2024-01-01 10:00:00');
        $contactMethods = [
            new ContactMethodDto('phone', 'mobile', '+33612345678', true),
        ];

        $command = new CreateClient(
            clinicId: '12345678-9abc-def0-1234-56789abcdef0',
            firstName: 'John',
            lastName: 'Doe',
            contactMethods: $contactMethods
        );

        $clientRepository = $this->createMock(ClientRepositoryInterface::class);
        $eventBus         = $this->createMock(EventBusInterface::class);
        $clock            = $this->createStub(ClockInterface::class);

        $clock->method('now')->willReturn($now);
        $clientRepository->expects(self::once())->method('save');
        $eventBus->expects(self::once())->method('publish');

        $handler = new CreateClientHandler($clientRepository, new DomainEventPublisher($eventBus), $clock, $this->createStub(ClientAnimalRegistryInterface::class));
        $result  = $handler($command);

        self::assertNotEmpty($result);
    }

    public function testHandleCreatesClientWithMultipleContactMethods(): void
    {
        $now            = new \DateTimeImmutable('2024-01-01 10:00:00');
        $contactMethods = [
            new ContactMethodDto('email', 'work', 'john@example.com', true),
            new ContactMethodDto('phone', 'mobile', '+33612345678', true),
            new ContactMethodDto('email', 'home', 'john.doe@personal.com', false),
        ];

        $command = new CreateClient(
            clinicId: '12345678-9abc-def0-1234-56789abcdef0',
            firstName: 'John',
            lastName: 'Doe',
            contactMethods: $contactMethods
        );

        $clientRepository = $this->createMock(ClientRepositoryInterface::class);
        $eventBus         = $this->createMock(EventBusInterface::class);
        $clock            = $this->createStub(ClockInterface::class);

        $clock->method('now')->willReturn($now);

        $clientRepository->expects(self::once())
            ->method('save')
            ->with(self::callback(function (Client $client): bool {
                return 3 === \count($client->contactMethods());
            }))
        ;

        $eventBus->expects(self::once())->method('publish');

        $handler = new CreateClientHandler($clientRepository, new DomainEventPublisher($eventBus), $clock, $this->createStub(ClientAnimalRegistryInterface::class));
        $result  = $handler($command);

        self::assertNotEmpty($result);
    }

    public function testOpensTheWholeRecordAndRegistersTheAnimalsOnceTheClientIsSaved(): void
    {
        $now     = new \DateTimeImmutable('2026-10-16 10:00:00');
        $animal  = new NewAnimalDto('Caramel', 'nac', 'female', 'neutered', false, 'Lapin bélier nain', '2022-03-14', null);
        $command = new CreateClient(
            clinicId: '12345678-9abc-def0-1234-56789abcdef0',
            firstName: 'Anne-Marie',
            lastName: 'Le Gallou',
            contactMethods: [new ContactMethodDto('phone', 'mobile', '+33698765412', true)],
            civility: 'madam',
            postalAddress: new PostalAddressDto('12 Rue de Rome', 'Paris', 'FR', 'Bât. B', '75008'),
            preferredChannel: 'sms',
            channelsByConsentKind: ['appointment_reminders' => ['sms'], 'marketing' => ['sms']],
            animals: [$animal, $animal],
        );

        $order            = [];
        $clientRepository = $this->createMock(ClientRepositoryInterface::class);
        $clientRepository->expects(self::once())
            ->method('save')
            ->with(self::callback(static function (Client $client) use (&$order): bool {
                $order[]     = 'save';
                $preferences = $client->communicationPreferences();

                return Civility::MADAM === $client->identity()->civility
                    && 'Bât. B' === $client->postalAddress()?->streetLine2
                    && '75008' === $client->postalAddress()->postalCode
                    && ContactChannel::SMS === $preferences->preferredChannel
                    && null !== $preferences->consentFor(ConsentKind::APPOINTMENT_REMINDERS)
                    && null !== $preferences->consentFor(ConsentKind::MARKETING)
                    && null === $preferences->consentFor(ConsentKind::BILLING);
            }))
        ;
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn($now);

        $registry = $this->createMock(ClientAnimalRegistryInterface::class);
        $registry->expects(self::exactly(2))
            ->method('register')
            ->willReturnCallback(static function (string $clinicId, string $ownerId, NewAnimalDto $dto) use (&$order, $animal): string {
                $order[] = 'animal';
                self::assertSame('12345678-9abc-def0-1234-56789abcdef0', $clinicId);
                self::assertSame($animal, $dto);

                return 'animal-id';
            })
        ;

        $handler  = new CreateClientHandler($clientRepository, new DomainEventPublisher($this->createStub(EventBusInterface::class)), $clock, $registry);
        $clientId = $handler($command);

        self::assertSame(['save', 'animal', 'animal'], $order);
        self::assertNotEmpty($clientId);
    }

    public function testAClientWithoutPreferencesKeepsTheDefaultOnes(): void
    {
        $clientRepository = $this->createMock(ClientRepositoryInterface::class);
        $clientRepository->expects(self::once())
            ->method('save')
            ->with(self::callback(static fn (Client $client): bool => null === $client->postalAddress()
                && null === $client->communicationPreferences()->preferredChannel
                && [] === $client->communicationPreferences()->consents))
        ;
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-10-16'));
        $registry = $this->createMock(ClientAnimalRegistryInterface::class);
        $registry->expects(self::never())->method('register');

        (new CreateClientHandler($clientRepository, new DomainEventPublisher($this->createStub(EventBusInterface::class)), $clock, $registry))(new CreateClient(
            clinicId: '12345678-9abc-def0-1234-56789abcdef0',
            firstName: 'John',
            lastName: 'Doe',
            contactMethods: [new ContactMethodDto('phone', 'mobile', '+33612345678', true)],
        ));
    }

    public function testOnlyConsentsCanBeGivenWithoutAPreferredChannel(): void
    {
        $clientRepository = $this->createMock(ClientRepositoryInterface::class);
        $clientRepository->expects(self::once())
            ->method('save')
            ->with(self::callback(static fn (Client $client): bool => null === $client->communicationPreferences()->preferredChannel
                && null !== $client->communicationPreferences()->consentFor(ConsentKind::MARKETING)))
        ;
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-10-16'));

        (new CreateClientHandler($clientRepository, new DomainEventPublisher($this->createStub(EventBusInterface::class)), $clock, $this->createStub(ClientAnimalRegistryInterface::class)))(new CreateClient(
            clinicId: '12345678-9abc-def0-1234-56789abcdef0',
            firstName: 'John',
            lastName: 'Doe',
            contactMethods: [new ContactMethodDto('email', 'home', 'john@example.com', true)],
            channelsByConsentKind: ['marketing' => ['email']],
        ));
    }
}
