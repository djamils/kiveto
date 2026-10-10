<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Infrastructure\Persistence\Doctrine\Mapper;

use App\Context\Client\Domain\ClientCommunication;
use App\Context\Client\Domain\ValueObject\ClientCommunicationId;
use App\Context\Client\Domain\ValueObject\ClientId;
use App\Context\Client\Domain\ValueObject\ClinicId;
use App\Context\Client\Domain\ValueObject\CommunicationDirection;
use App\Context\Client\Domain\ValueObject\ContactChannel;
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
}
