<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Infrastructure\Persistence\Doctrine\Mapper;

use App\Context\Client\Domain\ClientCommunication;
use App\Context\Client\Domain\ValueObject\ClientCommunicationId;
use App\Context\Client\Domain\ValueObject\ClientId;
use App\Context\Client\Domain\ValueObject\ClinicId;
use App\Context\Client\Domain\ValueObject\CommunicationDirection;
use App\Context\Client\Domain\ValueObject\CommunicationOrigin;
use App\Context\Client\Domain\ValueObject\ContactChannel;
use App\Context\Client\Domain\ValueObject\DeliveryStatus;
use App\Context\Client\Infrastructure\Persistence\Doctrine\Mapper\ClientCommunicationMapper;
use PHPUnit\Framework\TestCase;

final class ClientCommunicationMapperTest extends TestCase
{
    public function testACommunicationSurvivesARoundTrip(): void
    {
        $mapper        = new ClientCommunicationMapper();
        $occurredAt    = new \DateTimeImmutable('2026-03-02 09:12:00');
        $communication = ClientCommunication::reconstitute(
            ClientCommunicationId::fromString('0192a000-0000-7000-8000-000000000002'),
            ClinicId::fromString('12345678-9abc-def0-1234-56789abcdef0'),
            ClientId::fromString('01234567-89ab-cdef-0123-456789abcdef'),
            ContactChannel::PHONE,
            CommunicationDirection::INBOUND,
            'Appel entrant',
            'Prise de rendez-vous.',
            'Camille',
            $occurredAt,
        );

        $entity = $mapper->toEntity($communication);

        self::assertSame('0192a000-0000-7000-8000-000000000002', $entity->id->toString());
        self::assertSame('12345678-9abc-def0-1234-56789abcdef0', $entity->clinicId->toString());
        self::assertSame('01234567-89ab-cdef-0123-456789abcdef', $entity->clientId->toString());
        self::assertSame(ContactChannel::PHONE, $entity->channel);
        self::assertSame(CommunicationDirection::INBOUND, $entity->direction);
        self::assertSame('Appel entrant', $entity->subject);
        self::assertSame('Prise de rendez-vous.', $entity->body);
        self::assertSame('Camille', $entity->authorName);
        self::assertSame($occurredAt, $entity->occurredAt);

        $restored = $mapper->toDomain($entity);

        self::assertTrue($restored->id()->equals($communication->id()));
        self::assertTrue($restored->clinicId()->equals($communication->clinicId()));
        self::assertTrue($restored->clientId()->equals($communication->clientId()));
        self::assertSame($communication->channel(), $restored->channel());
        self::assertSame($communication->direction(), $restored->direction());
        self::assertSame($communication->subject(), $restored->subject());
        self::assertSame($communication->body(), $restored->body());
        self::assertSame($communication->authorName(), $restored->authorName());
        self::assertSame($communication->occurredAt(), $restored->occurredAt());
    }

    public function testAnAutomatedEntrySurvivesARoundTripAndOnlyItsStatusIsCopiedOnUpdate(): void
    {
        $mapper        = new ClientCommunicationMapper();
        $at            = new \DateTimeImmutable('2026-10-26 09:42:05');
        $communication = ClientCommunication::recordAutomated(
            ClientCommunicationId::fromString('0192a000-0000-7000-8000-000000000002'),
            ClinicId::fromString('12345678-9abc-def0-1234-56789abcdef0'),
            ClientId::fromString('01234567-89ab-cdef-0123-456789abcdef'),
            ContactChannel::SMS,
            'Rappel de rendez-vous',
            'À +33611223344',
            '0192a000-0000-7000-8000-0000000000e1',
            DeliveryStatus::SENT,
            2,
            $at,
        );

        $entity = $mapper->toEntity($communication);

        self::assertSame(CommunicationOrigin::AUTOMATED, $entity->origin);
        self::assertSame(DeliveryStatus::SENT, $entity->deliveryStatus);
        self::assertSame('0192a000-0000-7000-8000-0000000000e1', $entity->notificationMessageId?->toString());
        self::assertSame(2, $entity->deliveryStatusSequence);
        self::assertSame($at, $entity->statusChangedAt);

        $restored = $mapper->toDomain($entity);

        self::assertSame(CommunicationOrigin::AUTOMATED, $restored->origin());
        self::assertSame(DeliveryStatus::SENT, $restored->deliveryStatus());
        self::assertSame('0192a000-0000-7000-8000-0000000000e1', $restored->notificationMessageId());
        self::assertSame(2, $restored->deliveryStatusSequence());
        self::assertSame($at, $restored->statusChangedAt());

        $earlier   = new \DateTimeImmutable('2026-10-26 09:42:00');
        $delivered = new \DateTimeImmutable('2026-10-26 09:42:30');
        $restored->recordDeliveryStatus(DeliveryStatus::QUEUED, 1, $earlier);
        $restored->recordDeliveryStatus(DeliveryStatus::DELIVERED, 3, $delivered);
        $mapper->updateEntity($entity, $restored);

        self::assertSame($earlier, $entity->occurredAt);
        self::assertSame(DeliveryStatus::DELIVERED, $entity->deliveryStatus);
        self::assertSame(3, $entity->deliveryStatusSequence);
        self::assertSame($delivered, $entity->statusChangedAt);
        self::assertSame('Rappel de rendez-vous', $entity->subject);
    }
}
