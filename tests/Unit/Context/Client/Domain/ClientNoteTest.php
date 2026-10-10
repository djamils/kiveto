<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Domain;

use App\Context\Client\Domain\ClientNote;
use App\Context\Client\Domain\Event\ClientNoteAdded;
use App\Context\Client\Domain\ValueObject\ClientId;
use App\Context\Client\Domain\ValueObject\ClientNoteId;
use App\Context\Client\Domain\ValueObject\ClientNoteKind;
use App\Context\Client\Domain\ValueObject\ClinicId;
use PHPUnit\Framework\TestCase;

final class ClientNoteTest extends TestCase
{
    private const string NOTE_ID   = '0192a000-0000-7000-8000-000000000001';
    private const string CLIENT_ID = '01234567-89ab-cdef-0123-456789abcdef';
    private const string CLINIC_ID = '12345678-9abc-def0-1234-56789abcdef0';

    public function testAddBuildsTheNoteAndRecordsItsEvent(): void
    {
        $createdAt = new \DateTimeImmutable('2026-07-22 09:15:00');

        $note = $this->note(ClientNote::add(...), '  Ne pas aborder le litige en salle d’attente. ', ' Camille ', $createdAt);

        self::assertSame(self::NOTE_ID, $note->id()->toString());
        self::assertSame(self::CLINIC_ID, $note->clinicId()->toString());
        self::assertSame(self::CLIENT_ID, $note->clientId()->toString());
        self::assertSame(ClientNoteKind::ATTENTION, $note->kind());
        self::assertSame('Ne pas aborder le litige en salle d’attente.', $note->content());
        self::assertSame('Camille', $note->authorName());
        self::assertSame($createdAt, $note->createdAt());

        $events = $note->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ClientNoteAdded::class, $events[0]);
        self::assertSame(self::NOTE_ID, $events[0]->aggregateId());
        self::assertSame([
            'noteId'   => self::NOTE_ID,
            'clientId' => self::CLIENT_ID,
            'clinicId' => self::CLINIC_ID,
            'kind'     => 'attention',
        ], $events[0]->payload());
    }

    public function testReconstituteDoesNotRecordEvents(): void
    {
        $note = $this->note(ClientNote::reconstitute(...), 'Cliente observante.', 'Dr Rousseau', new \DateTimeImmutable());

        self::assertSame([], $note->pullDomainEvents());
    }

    public function testRejectsAnEmptyNote(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->note(ClientNote::add(...), "  \n ", 'Camille', new \DateTimeImmutable());
    }

    public function testRejectsANoteThatIsTooLong(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->note(
            ClientNote::add(...),
            str_repeat('a', ClientNote::MAX_CONTENT_LENGTH + 1),
            'Camille',
            new \DateTimeImmutable(),
        );
    }

    public function testRejectsANoteWithoutAuthor(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->note(ClientNote::add(...), 'Cliente observante.', ' ', new \DateTimeImmutable());
    }

    /**
     * @param \Closure(ClientNoteId, ClinicId, ClientId, ClientNoteKind, string, string, \DateTimeImmutable): ClientNote $factory
     */
    private function note(\Closure $factory, string $content, string $author, \DateTimeImmutable $createdAt): ClientNote
    {
        return $factory(
            ClientNoteId::fromString(self::NOTE_ID),
            ClinicId::fromString(self::CLINIC_ID),
            ClientId::fromString(self::CLIENT_ID),
            ClientNoteKind::ATTENTION,
            $content,
            $author,
            $createdAt,
        );
    }
}
