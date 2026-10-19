<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Application\Command\CreateClient;

use App\Context\Client\Application\Command\CreateClient\ClientTaxProfileDto;
use App\Context\Client\Application\Command\CreateClient\ContactMethodDto;
use App\Context\Client\Application\Command\CreateClient\CreateClient;
use App\Context\Client\Application\Command\CreateClient\CreateClientHandler;
use App\Context\Client\Application\Port\ClientAnimalRegistryInterface;
use App\Context\Client\Domain\Client;
use App\Context\Client\Domain\Event\ClientCreated;
use App\Context\Client\Domain\Event\ClientTaxIdentifiersChanged;
use App\Context\Client\Domain\Repository\ClientRepositoryInterface;
use App\Context\Client\Domain\ValueObject\ClientTaxStatus;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use PHPUnit\Framework\TestCase;

final class CreateClientHandlerTaxProfileTest extends TestCase
{
    public function testAStructureIsOpenedWithItsTaxProfileInOneGo(): void
    {
        $saved = null;

        $repository = $this->createMock(ClientRepositoryInterface::class);
        $repository->expects(self::once())->method('save')->willReturnCallback(static function (Client $client) use (&$saved): void {
            $saved = $client;
        });

        $published = [];
        $eventBus  = $this->createMock(EventBusInterface::class);
        $eventBus->expects(self::once())->method('publish')->willReturnCallback(static function (array $stamps, object ...$events) use (&$published): void {
            $published = $events;
        });

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-10-17 10:00:00'));

        $handler = new CreateClientHandler($repository, new DomainEventPublisher($eventBus), $clock, $this->createStub(ClientAnimalRegistryInterface::class));

        $handler(new CreateClient(
            clinicId: '12345678-9abc-def0-1234-56789abcdef0',
            firstName: 'Claire',
            lastName: 'Rostand',
            contactMethods: [new ContactMethodDto('phone', 'work', '+33491000000', true)],
            civility: 'madam',
            taxProfile: new ClientTaxProfileDto('french_business', 'Élevage des Calanques', '523456788', 'FR31523456788'),
        ));

        self::assertInstanceOf(Client::class, $saved);
        self::assertSame(ClientTaxStatus::FRENCH_BUSINESS, $saved->taxProfile()->status());
        self::assertSame('FR31523456788', $saved->taxProfile()->vatNumber()?->value());
        self::assertCount(2, $published);
        self::assertInstanceOf(ClientCreated::class, $published[0]);
        self::assertSame('Élevage des Calanques', $published[0]->payload()['legalName']);
        self::assertInstanceOf(ClientTaxIdentifiersChanged::class, $published[1]);
    }
}
