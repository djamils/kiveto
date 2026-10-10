<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Application\Command\ChangeClientAuthorizedPerson;

use App\Context\Client\Application\Command\ChangeClientAuthorizedPerson\AuthorizedPersonDto;
use App\Context\Client\Application\Command\ChangeClientAuthorizedPerson\ChangeClientAuthorizedPerson;
use App\Context\Client\Application\Command\ChangeClientAuthorizedPerson\ChangeClientAuthorizedPersonHandler;
use App\Context\Client\Domain\Client;
use App\Context\Client\Domain\Repository\ClientRepositoryInterface;
use App\Context\Client\Domain\ValueObject\AuthorizedPerson;
use App\Context\Client\Domain\ValueObject\AuthorizedPersonRelationship;
use App\Context\Client\Domain\ValueObject\Civility;
use App\Context\Client\Domain\ValueObject\ClientId;
use App\Context\Client\Domain\ValueObject\ClientIdentity;
use App\Context\Client\Domain\ValueObject\ClientStatus;
use App\Context\Client\Domain\ValueObject\ClinicId;
use App\Context\Client\Domain\ValueObject\ContactLabel;
use App\Context\Client\Domain\ValueObject\ContactMethod;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\EmailAddress;
use PHPUnit\Framework\TestCase;

final class ChangeClientAuthorizedPersonHandlerTest extends TestCase
{
    private const string CLIENT_ID = '01234567-89ab-cdef-0123-456789abcdef';
    private const string CLINIC_ID = '12345678-9abc-def0-1234-56789abcdef0';

    public function testDesignatesTheAuthorizedPerson(): void
    {
        $now    = new \DateTimeImmutable('2026-09-30 09:00:00');
        $client = $this->client();

        $this->handler($client, $now, 1)(new ChangeClientAuthorizedPerson(
            clinicId: self::CLINIC_ID,
            clientId: self::CLIENT_ID,
            authorizedPerson: new AuthorizedPersonDto(
                civility: 'mister',
                firstName: 'Marc',
                lastName: 'Lambert',
                relationship: 'spouse',
                phoneNumber: '+33688214509',
            ),
        ));

        $person = $client->authorizedPerson();
        self::assertNotNull($person);
        self::assertSame(Civility::MISTER, $person->civility);
        self::assertSame('Marc Lambert', $person->fullName());
        self::assertSame(AuthorizedPersonRelationship::SPOUSE, $person->relationship);
        self::assertSame('+33688214509', $person->phoneNumber?->toString());
        self::assertSame($now, $person->authorizedAt);
    }

    public function testDesignatesAPersonWithoutPhoneNumber(): void
    {
        $client = $this->client();

        $this->handler($client, new \DateTimeImmutable('2026-09-30'), 1)(new ChangeClientAuthorizedPerson(
            clinicId: self::CLINIC_ID,
            clientId: self::CLIENT_ID,
            authorizedPerson: new AuthorizedPersonDto('madam', 'Julie', 'Lambert', 'child'),
        ));

        self::assertNull($client->authorizedPerson()?->phoneNumber);
        self::assertSame('Julie Lambert', $client->authorizedPerson()?->fullName());
    }

    public function testWithdrawsTheAuthorization(): void
    {
        $now    = new \DateTimeImmutable('2026-09-30 09:00:00');
        $client = $this->client();
        $client->changeAuthorizedPerson(new AuthorizedPerson(
            Civility::MISTER,
            'Marc',
            'Lambert',
            AuthorizedPersonRelationship::SPOUSE,
            null,
            $now,
        ), $now);
        self::assertCount(1, $client->pullDomainEvents());

        $this->handler($client, $now, 1)(new ChangeClientAuthorizedPerson(self::CLINIC_ID, self::CLIENT_ID));

        self::assertNull($client->authorizedPerson());
    }

    public function testRejectsAnUnknownRelationship(): void
    {
        $this->expectException(\ValueError::class);

        $this->handler($this->client(), new \DateTimeImmutable(), 0)(new ChangeClientAuthorizedPerson(
            clinicId: self::CLINIC_ID,
            clientId: self::CLIENT_ID,
            authorizedPerson: new AuthorizedPersonDto('mister', 'Marc', 'Lambert', 'stranger'),
        ));
    }

    private function handler(Client $client, \DateTimeImmutable $now, int $saves): ChangeClientAuthorizedPersonHandler
    {
        $clients = $this->createMock(ClientRepositoryInterface::class);
        $clients->expects(self::once())
            ->method('get')
            ->with(
                self::callback(static fn (ClinicId $id): bool => self::CLINIC_ID === $id->toString()),
                self::callback(static fn (ClientId $id): bool => self::CLIENT_ID === $id->toString()),
            )
            ->willReturn($client)
        ;
        $clients->expects(self::exactly($saves))->method('save')->with($client);

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn($now);

        $eventBus = $this->createMock(EventBusInterface::class);
        $eventBus->expects(self::exactly($saves))->method('publish');

        return new ChangeClientAuthorizedPersonHandler($clients, new DomainEventPublisher($eventBus), $clock);
    }

    private function client(): Client
    {
        $createdAt = new \DateTimeImmutable('2019-03-12 10:00:00');

        return Client::reconstitute(
            ClientId::fromString(self::CLIENT_ID),
            ClinicId::fromString(self::CLINIC_ID),
            new ClientIdentity('Hélène', 'Lambert'),
            ClientStatus::ACTIVE,
            [ContactMethod::email(EmailAddress::fromString('h.lambert@example.com'), ContactLabel::HOME, true)],
            $createdAt,
            $createdAt,
        );
    }
}
