<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Domain;

use App\Context\Client\Domain\ClientCommunication;
use App\Context\Client\Domain\Event\ClientCommunicationLogged;
use App\Context\Client\Domain\ValueObject\ClientCommunicationId;
use App\Context\Client\Domain\ValueObject\ClientId;
use App\Context\Client\Domain\ValueObject\ClinicId;
use App\Context\Client\Domain\ValueObject\CommunicationDirection;
use App\Context\Client\Domain\ValueObject\ContactChannel;
use PHPUnit\Framework\TestCase;

final class ClientCommunicationTest extends TestCase
{
    private const string COMMUNICATION_ID = '0192a000-0000-7000-8000-000000000002';
    private const string CLIENT_ID        = '01234567-89ab-cdef-0123-456789abcdef';
    private const string CLINIC_ID        = '12345678-9abc-def0-1234-56789abcdef0';

    public function testLogBuildsTheEntryAndRecordsItsEvent(): void
    {
        $occurredAt = new \DateTimeImmutable('2026-03-02 09:12:00');

        $communication = $this->communication(' Appel entrant ', ' Prise de rendez-vous. ', ' Camille ', $occurredAt);

        self::assertSame(self::COMMUNICATION_ID, $communication->id()->toString());
        self::assertSame(self::CLINIC_ID, $communication->clinicId()->toString());
        self::assertSame(self::CLIENT_ID, $communication->clientId()->toString());
        self::assertSame(ContactChannel::PHONE, $communication->channel());
        self::assertSame(CommunicationDirection::INBOUND, $communication->direction());
        self::assertSame('Appel entrant', $communication->subject());
        self::assertSame('Prise de rendez-vous.', $communication->body());
        self::assertSame('Camille', $communication->authorName());
        self::assertSame($occurredAt, $communication->occurredAt());

        $events = $communication->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ClientCommunicationLogged::class, $events[0]);
        self::assertSame(self::COMMUNICATION_ID, $events[0]->aggregateId());
        self::assertSame([
            'communicationId' => self::COMMUNICATION_ID,
            'clientId'        => self::CLIENT_ID,
            'clinicId'        => self::CLINIC_ID,
            'channel'         => 'phone',
            'direction'       => 'inbound',
        ], $events[0]->payload());
    }

    public function testAnEmptyBodyIsStoredAsNull(): void
    {
        self::assertNull($this->communication('Appel entrant', '   ', 'Camille')->body());
        self::assertNull($this->communication('Appel entrant', null, 'Camille')->body());
    }

    public function testReconstituteDoesNotRecordEvents(): void
    {
        $communication = ClientCommunication::reconstitute(
            ClientCommunicationId::fromString(self::COMMUNICATION_ID),
            ClinicId::fromString(self::CLINIC_ID),
            ClientId::fromString(self::CLIENT_ID),
            ContactChannel::EMAIL,
            CommunicationDirection::OUTBOUND,
            'Relance',
            null,
            'Camille',
            new \DateTimeImmutable(),
        );

        self::assertSame([], $communication->pullDomainEvents());
    }

    public function testRejectsAnEmptySubject(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->communication('  ', null, 'Camille');
    }

    public function testRejectsASubjectThatIsTooLong(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->communication(str_repeat('a', ClientCommunication::MAX_SUBJECT_LENGTH + 1), null, 'Camille');
    }

    public function testRejectsABodyThatIsTooLong(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->communication('Appel', str_repeat('a', ClientCommunication::MAX_BODY_LENGTH + 1), 'Camille');
    }

    public function testRejectsAnEntryWithoutAuthor(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->communication('Appel', null, '');
    }

    private function communication(
        string $subject,
        ?string $body,
        string $author,
        ?\DateTimeImmutable $occurredAt = null,
    ): ClientCommunication {
        return ClientCommunication::log(
            ClientCommunicationId::fromString(self::COMMUNICATION_ID),
            ClinicId::fromString(self::CLINIC_ID),
            ClientId::fromString(self::CLIENT_ID),
            ContactChannel::PHONE,
            CommunicationDirection::INBOUND,
            $subject,
            $body,
            $author,
            $occurredAt ?? new \DateTimeImmutable(),
        );
    }
}
