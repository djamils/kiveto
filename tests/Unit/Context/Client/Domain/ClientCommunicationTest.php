<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Domain;

use App\Context\Client\Domain\ClientCommunication;
use App\Context\Client\Domain\Event\ClientCommunicationLogged;
use App\Context\Client\Domain\Exception\ManualCommunicationCannotChangeException;
use App\Context\Client\Domain\ValueObject\ClientCommunicationId;
use App\Context\Client\Domain\ValueObject\ClientId;
use App\Context\Client\Domain\ValueObject\ClinicId;
use App\Context\Client\Domain\ValueObject\CommunicationDirection;
use App\Context\Client\Domain\ValueObject\CommunicationOrigin;
use App\Context\Client\Domain\ValueObject\ContactChannel;
use App\Context\Client\Domain\ValueObject\DeliveryStatus;
use PHPUnit\Framework\TestCase;

final class ClientCommunicationTest extends TestCase
{
    private const string COMMUNICATION_ID = '0192a000-0000-7000-8000-000000000002';
    private const string CLIENT_ID        = '01234567-89ab-cdef-0123-456789abcdef';
    private const string CLINIC_ID        = '12345678-9abc-def0-1234-56789abcdef0';
    private const string MESSAGE_ID       = '0192a000-0000-7000-8000-0000000000e1';

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

    public function testAManualEntryIsManualAndTracksNoDelivery(): void
    {
        $communication = $this->communication('Appel', null, 'Camille');

        self::assertSame(CommunicationOrigin::MANUAL, $communication->origin());
        self::assertFalse($communication->isAutomated());
        self::assertNull($communication->deliveryStatus());
        self::assertNull($communication->notificationMessageId());
        self::assertNull($communication->deliveryStatusSequence());
        self::assertNull($communication->statusChangedAt());
    }

    public function testAManualEntryCannotChangeItsStatus(): void
    {
        $this->expectException(ManualCommunicationCannotChangeException::class);
        $this->expectExceptionMessage(self::COMMUNICATION_ID);

        $this->communication('Appel', null, 'Camille')->recordDeliveryStatus(DeliveryStatus::DELIVERED, 2, new \DateTimeImmutable());
    }

    public function testRecordAutomatedJournalsAnOutboundEntryByKiveto(): void
    {
        $at            = new \DateTimeImmutable('2026-10-26 09:42:00');
        $communication = $this->automated(DeliveryStatus::QUEUED, 1, $at, ' ' . str_repeat('é', 170));

        self::assertSame(CommunicationOrigin::AUTOMATED, $communication->origin());
        self::assertTrue($communication->isAutomated());
        self::assertSame(ContactChannel::EMAIL, $communication->channel());
        self::assertSame(CommunicationDirection::OUTBOUND, $communication->direction());
        self::assertSame(str_repeat('é', ClientCommunication::MAX_SUBJECT_LENGTH), $communication->subject(), 'a long e-mail subject is cut, not refused');
        self::assertSame('À h.lambert@example.com', $communication->body());
        self::assertSame('Envoi automatique', $communication->authorName());
        self::assertSame(DeliveryStatus::QUEUED, $communication->deliveryStatus());
        self::assertSame(self::MESSAGE_ID, $communication->notificationMessageId());
        self::assertSame(1, $communication->deliveryStatusSequence());
        self::assertSame($at, $communication->occurredAt());
        self::assertSame($at, $communication->statusChangedAt());
        self::assertSame([], $communication->pullDomainEvents());
    }

    public function testOnlyANewerStatusReplacesTheCurrentOne(): void
    {
        $sent          = new \DateTimeImmutable('2026-10-26 09:42:05');
        $communication = $this->automated(DeliveryStatus::SENT, 2, $sent);

        $delivered = new \DateTimeImmutable('2026-10-26 09:42:30');
        self::assertTrue($communication->recordDeliveryStatus(DeliveryStatus::DELIVERED, 3, $delivered));
        self::assertSame(DeliveryStatus::DELIVERED, $communication->deliveryStatus());
        self::assertSame(3, $communication->deliveryStatusSequence());
        self::assertSame($delivered, $communication->statusChangedAt());
        self::assertSame($sent, $communication->occurredAt());

        // A replay or a late report changes nothing.
        self::assertFalse($communication->recordDeliveryStatus(DeliveryStatus::DELIVERED, 3, $delivered));
        self::assertFalse($communication->recordDeliveryStatus(DeliveryStatus::SENT, 2, $sent));
        self::assertSame(DeliveryStatus::DELIVERED, $communication->deliveryStatus());
    }

    public function testALateFirstStatusDatesTheEntryEarlierWithoutReplacingTheStatus(): void
    {
        $communication = $this->automated(DeliveryStatus::SENT, 2, new \DateTimeImmutable('2026-10-26 09:42:05'));
        $queued        = new \DateTimeImmutable('2026-10-26 09:42:00');

        self::assertTrue($communication->recordDeliveryStatus(DeliveryStatus::QUEUED, 1, $queued));
        self::assertSame($queued, $communication->occurredAt());
        self::assertSame(DeliveryStatus::SENT, $communication->deliveryStatus());
        self::assertSame(2, $communication->deliveryStatusSequence());
    }

    public function testAnAutomatedEntryNeedsItsWholeDeliveryTracking(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ClientCommunication::reconstitute(
            ClientCommunicationId::fromString(self::COMMUNICATION_ID),
            ClinicId::fromString(self::CLINIC_ID),
            ClientId::fromString(self::CLIENT_ID),
            ContactChannel::SMS,
            CommunicationDirection::OUTBOUND,
            'Rappel de rendez-vous',
            null,
            ClientCommunication::AUTOMATED_AUTHOR,
            new \DateTimeImmutable(),
            CommunicationOrigin::AUTOMATED,
            DeliveryStatus::SENT,
            self::MESSAGE_ID,
        );
    }

    public function testAManualEntryCannotTrackADelivery(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ClientCommunication::reconstitute(
            ClientCommunicationId::fromString(self::COMMUNICATION_ID),
            ClinicId::fromString(self::CLINIC_ID),
            ClientId::fromString(self::CLIENT_ID),
            ContactChannel::SMS,
            CommunicationDirection::OUTBOUND,
            'Rappel',
            null,
            'Camille',
            new \DateTimeImmutable(),
            deliveryStatus: DeliveryStatus::SENT,
        );
    }

    public function testDeliveryStatusesReadWithoutColour(): void
    {
        self::assertSame(
            ['En cours', 'Envoyé', 'Différé', 'Délivré', 'Non délivré', 'Rejeté', 'Échec', 'Bloqué (plafond)'],
            array_map(static fn (DeliveryStatus $status): string => $status->label(), DeliveryStatus::cases()),
        );
    }

    private function automated(DeliveryStatus $status, int $sequence, \DateTimeImmutable $at, string $subject = 'Rendez-vous confirmé — Milo'): ClientCommunication
    {
        return ClientCommunication::recordAutomated(
            ClientCommunicationId::fromString(self::COMMUNICATION_ID),
            ClinicId::fromString(self::CLINIC_ID),
            ClientId::fromString(self::CLIENT_ID),
            ContactChannel::EMAIL,
            $subject,
            'À h.lambert@example.com',
            self::MESSAGE_ID,
            $status,
            $sequence,
            $at,
        );
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
