<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Application\Command\AddClientNote;

use App\Context\Client\Application\Command\AddClientNote\AddClientNote;
use App\Context\Client\Application\Command\AddClientNote\AddClientNoteHandler;
use App\Context\Client\Domain\Client;
use App\Context\Client\Domain\ClientNote;
use App\Context\Client\Domain\Exception\ClientNotFoundException;
use App\Context\Client\Domain\Repository\ClientNoteRepositoryInterface;
use App\Context\Client\Domain\Repository\ClientRepositoryInterface;
use App\Context\Client\Domain\ValueObject\ClientId;
use App\Context\Client\Domain\ValueObject\ClientIdentity;
use App\Context\Client\Domain\ValueObject\ClientNoteKind;
use App\Context\Client\Domain\ValueObject\ClientStatus;
use App\Context\Client\Domain\ValueObject\ClinicId;
use App\Context\Client\Domain\ValueObject\ContactLabel;
use App\Context\Client\Domain\ValueObject\ContactMethod;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\EmailAddress;
use PHPUnit\Framework\TestCase;

final class AddClientNoteHandlerTest extends TestCase
{
    private const string NOTE_ID   = '0192a000-0000-7000-8000-000000000001';
    private const string CLIENT_ID = '01234567-89ab-cdef-0123-456789abcdef';
    private const string CLINIC_ID = '12345678-9abc-def0-1234-56789abcdef0';

    public function testAddsTheNoteToAClientOfTheClinic(): void
    {
        $now = new \DateTimeImmutable('2026-07-22 09:15:00');

        $clients = $this->createMock(ClientRepositoryInterface::class);
        $clients->expects(self::once())
            ->method('get')
            ->with(
                self::callback(static fn (ClinicId $id): bool => self::CLINIC_ID === $id->toString()),
                self::callback(static fn (ClientId $id): bool => self::CLIENT_ID === $id->toString()),
            )
            ->willReturn($this->client())
        ;

        $saved = null;
        $notes = $this->createMock(ClientNoteRepositoryInterface::class);
        $notes->expects(self::once())
            ->method('save')
            ->willReturnCallback(static function (ClientNote $note) use (&$saved): void {
                $saved = $note;
            })
        ;

        $uuids = $this->createStub(UuidGeneratorInterface::class);
        $uuids->method('generate')->willReturn(self::NOTE_ID);

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn($now);

        $eventBus = $this->createMock(EventBusInterface::class);
        $eventBus->expects(self::once())->method('publish');

        $handler = new AddClientNoteHandler($clients, $notes, $uuids, new DomainEventPublisher($eventBus), $clock);

        $noteId = $handler(new AddClientNote(
            clinicId: self::CLINIC_ID,
            clientId: self::CLIENT_ID,
            kind: 'attention',
            content: 'Ne pas relancer avant clarification.',
            authorName: 'Camille',
        ));

        self::assertSame(self::NOTE_ID, $noteId);
        self::assertInstanceOf(ClientNote::class, $saved);
        self::assertSame(self::NOTE_ID, $saved->id()->toString());
        self::assertSame(self::CLIENT_ID, $saved->clientId()->toString());
        self::assertSame(self::CLINIC_ID, $saved->clinicId()->toString());
        self::assertSame(ClientNoteKind::ATTENTION, $saved->kind());
        self::assertSame('Ne pas relancer avant clarification.', $saved->content());
        self::assertSame('Camille', $saved->authorName());
        self::assertSame($now, $saved->createdAt());
    }

    public function testRefusesAClientThatIsNotInTheClinic(): void
    {
        $clients = $this->createStub(ClientRepositoryInterface::class);
        $clients->method('get')->willThrowException(new ClientNotFoundException(self::CLIENT_ID));

        $notes = $this->createMock(ClientNoteRepositoryInterface::class);
        $notes->expects(self::never())->method('save');

        $handler = new AddClientNoteHandler(
            $clients,
            $notes,
            $this->createStub(UuidGeneratorInterface::class),
            new DomainEventPublisher($this->createStub(EventBusInterface::class)),
            $this->createStub(ClockInterface::class),
        );

        $this->expectException(ClientNotFoundException::class);

        $handler(new AddClientNote(self::CLINIC_ID, self::CLIENT_ID, 'information', 'Note', 'Camille'));
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
