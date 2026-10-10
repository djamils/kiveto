<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Infrastructure\Persistence\Doctrine\Mapper;

use App\Context\Client\Domain\ClientNote;
use App\Context\Client\Domain\ValueObject\ClientId;
use App\Context\Client\Domain\ValueObject\ClientNoteId;
use App\Context\Client\Domain\ValueObject\ClientNoteKind;
use App\Context\Client\Domain\ValueObject\ClinicId;
use App\Context\Client\Infrastructure\Persistence\Doctrine\Mapper\ClientNoteMapper;
use PHPUnit\Framework\TestCase;

final class ClientNoteMapperTest extends TestCase
{
    public function testANoteSurvivesARoundTrip(): void
    {
        $mapper    = new ClientNoteMapper();
        $createdAt = new \DateTimeImmutable('2026-07-22 09:15:00');
        $note      = ClientNote::reconstitute(
            ClientNoteId::fromString('0192a000-0000-7000-8000-000000000001'),
            ClinicId::fromString('12345678-9abc-def0-1234-56789abcdef0'),
            ClientId::fromString('01234567-89ab-cdef-0123-456789abcdef'),
            ClientNoteKind::ATTENTION,
            'Litige en cours.',
            'Camille',
            $createdAt,
        );

        $entity = $mapper->toEntity($note);

        self::assertSame('0192a000-0000-7000-8000-000000000001', $entity->id->toString());
        self::assertSame('12345678-9abc-def0-1234-56789abcdef0', $entity->clinicId->toString());
        self::assertSame('01234567-89ab-cdef-0123-456789abcdef', $entity->clientId->toString());
        self::assertSame(ClientNoteKind::ATTENTION, $entity->kind);
        self::assertSame('Litige en cours.', $entity->content);
        self::assertSame('Camille', $entity->authorName);
        self::assertSame($createdAt, $entity->createdAt);

        $restored = $mapper->toDomain($entity);

        self::assertTrue($restored->id()->equals($note->id()));
        self::assertTrue($restored->clinicId()->equals($note->clinicId()));
        self::assertTrue($restored->clientId()->equals($note->clientId()));
        self::assertSame($note->kind(), $restored->kind());
        self::assertSame($note->content(), $restored->content());
        self::assertSame($note->authorName(), $restored->authorName());
        self::assertSame($note->createdAt(), $restored->createdAt());
    }
}
