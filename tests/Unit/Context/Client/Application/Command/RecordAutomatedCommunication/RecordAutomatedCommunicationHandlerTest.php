<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Application\Command\RecordAutomatedCommunication;

use App\Context\Client\Application\Command\RecordAutomatedCommunication\RecordAutomatedCommunication;
use App\Context\Client\Application\Command\RecordAutomatedCommunication\RecordAutomatedCommunicationHandler;
use App\Context\Client\Domain\Client;
use App\Context\Client\Domain\ClientCommunication;
use App\Context\Client\Domain\Exception\ClientNotFoundException;
use App\Context\Client\Domain\Repository\ClientRepositoryInterface;
use App\Context\Client\Domain\ValueObject\ClientId;
use App\Context\Client\Domain\ValueObject\ClientIdentity;
use App\Context\Client\Domain\ValueObject\ClientStatus;
use App\Context\Client\Domain\ValueObject\ClinicId;
use App\Context\Client\Domain\ValueObject\CommunicationDirection;
use App\Context\Client\Domain\ValueObject\ContactChannel;
use App\Context\Client\Domain\ValueObject\ContactLabel;
use App\Context\Client\Domain\ValueObject\ContactMethod;
use App\Context\Client\Domain\ValueObject\DeliveryStatus;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\ValueObject\EmailAddress;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class RecordAutomatedCommunicationHandlerTest extends TestCase
{
    private const string MESSAGE_ID = '0192a000-0000-7000-8000-0000000000e1';
    private const string CLIENT_ID  = '01234567-89ab-cdef-0123-456789abcdef';
    private const string CLINIC_ID  = '12345678-9abc-def0-1234-56789abcdef0';

    private InMemoryClientCommunications $journal;

    protected function setUp(): void
    {
        $this->journal = new InMemoryClientCommunications();
    }

    public function testTheFirstStatusJournalsTheMessage(): void
    {
        $this->handler()($this->command('QUEUED', 1, '2026-10-26T10:42:00+01:00'));

        self::assertCount(1, $this->journal->entries);
        $entry = $this->entry();
        self::assertSame('0192a000-0000-7000-8000-000000000101', $entry->id()->toString());
        self::assertSame(self::CLIENT_ID, $entry->clientId()->toString());
        self::assertSame(self::CLINIC_ID, $entry->clinicId()->toString());
        self::assertSame(ContactChannel::EMAIL, $entry->channel());
        self::assertSame(CommunicationDirection::OUTBOUND, $entry->direction());
        self::assertSame('Rendez-vous confirmé — Milo, jeudi 22 octobre à 9h30', $entry->subject());
        self::assertSame('Destinataire : Hélène Lambert', $entry->body());
        self::assertSame('Envoi automatique', $entry->authorName());
        self::assertSame(DeliveryStatus::QUEUED, $entry->deliveryStatus());
        self::assertSame(1, $entry->deliveryStatusSequence());
        self::assertSame('2026-10-26 09:42:00 UTC', $entry->occurredAt()->format('Y-m-d H:i:s T'));
        self::assertSame(0, $this->journal->saves);
    }

    public function testLaterStatusesFollowTheSequenceWhateverTheirOrder(): void
    {
        $handler = $this->handler();

        $handler($this->command('DELIVERED', 3, '2026-10-26T09:42:30+00:00'));
        $handler($this->command('QUEUED', 1, '2026-10-26T09:42:00+00:00'));
        $handler($this->command('SENT', 2, '2026-10-26T09:42:05+00:00'));
        $handler($this->command('DELIVERED', 3, '2026-10-26T09:42:30+00:00'));

        self::assertCount(1, $this->journal->entries);
        self::assertSame(DeliveryStatus::DELIVERED, $this->entry()->deliveryStatus());
        self::assertSame('2026-10-26 09:42:00', $this->entry()->occurredAt()->format('Y-m-d H:i:s'), 'dated by its first status');
        self::assertSame(1, $this->journal->saves, 'only the late first status changed the entry');
    }

    public function testAnSmsIsJournaledOnTheSmsChannelWithoutRecipientWhenUnnamed(): void
    {
        $this->handler()(new RecordAutomatedCommunication(self::MESSAGE_ID, self::CLINIC_ID, self::CLIENT_ID, 'SMS', 'Rappel de rendez-vous', ' ', 'BLOCKED_CAP', '2026-10-26T09:42:00+00:00', 1));

        self::assertSame(ContactChannel::SMS, $this->entry()->channel());
        self::assertNull($this->entry()->body());
        self::assertSame(DeliveryStatus::BLOCKED_CAP, $this->entry()->deliveryStatus());
    }

    public function testAClientUnknownToTheClinicIsNotJournaled(): void
    {
        $clients = $this->createStub(ClientRepositoryInterface::class);
        $clients->method('get')->willThrowException(new ClientNotFoundException(self::CLIENT_ID));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');

        $this->handler($clients, $logger)($this->command('SENT', 2, '2026-10-26T09:42:05+00:00'));

        self::assertSame([], $this->journal->entries);
    }

    private function handler(?ClientRepositoryInterface $clients = null, ?LoggerInterface $logger = null): RecordAutomatedCommunicationHandler
    {
        if (null === $clients) {
            $clients = $this->createStub(ClientRepositoryInterface::class);
            $clients->method('get')->willReturn($this->client());
        }

        $uuids = $this->createStub(UuidGeneratorInterface::class);
        $uuids->method('generate')->willReturn('0192a000-0000-7000-8000-000000000101');

        return new RecordAutomatedCommunicationHandler($clients, $this->journal, $uuids, $logger ?? new NullLogger());
    }

    private function command(string $status, int $sequence, string $at): RecordAutomatedCommunication
    {
        return new RecordAutomatedCommunication(
            notificationMessageId: self::MESSAGE_ID,
            clinicId: self::CLINIC_ID,
            clientId: self::CLIENT_ID,
            channel: 'EMAIL',
            subject: 'Rendez-vous confirmé — Milo, jeudi 22 octobre à 9h30',
            recipientLabel: 'Hélène Lambert',
            status: $status,
            occurredAt: $at,
            sequence: $sequence,
        );
    }

    private function entry(): ClientCommunication
    {
        $entry = $this->journal->findByNotificationMessageId(self::MESSAGE_ID);
        self::assertNotNull($entry);

        return $entry;
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
