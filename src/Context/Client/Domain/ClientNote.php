<?php

declare(strict_types=1);

namespace App\Context\Client\Domain;

use App\Context\Client\Domain\Event\ClientNoteAdded;
use App\Context\Client\Domain\ValueObject\ClientId;
use App\Context\Client\Domain\ValueObject\ClientNoteId;
use App\Context\Client\Domain\ValueObject\ClientNoteKind;
use App\Context\Client\Domain\ValueObject\ClinicId;
use App\Shared\Domain\Aggregate\AggregateRoot;

/**
 * An internal note about a client. Never shown to the client, never edited:
 * the staff adds a new note instead.
 */
final class ClientNote extends AggregateRoot
{
    public const int MAX_CONTENT_LENGTH = 2000;

    private ClientNoteId $id;
    private ClinicId $clinicId;
    private ClientId $clientId;
    private ClientNoteKind $kind;
    private string $content;
    private string $authorName;
    private \DateTimeImmutable $createdAt;

    private function __construct()
    {
    }

    public static function add(
        ClientNoteId $id,
        ClinicId $clinicId,
        ClientId $clientId,
        ClientNoteKind $kind,
        string $content,
        string $authorName,
        \DateTimeImmutable $createdAt,
    ): self {
        $note = self::reconstitute($id, $clinicId, $clientId, $kind, $content, $authorName, $createdAt);

        $note->recordDomainEvent(new ClientNoteAdded(
            noteId: $id->toString(),
            clientId: $clientId->toString(),
            clinicId: $clinicId->toString(),
            kind: $kind->value,
        ));

        return $note;
    }

    public static function reconstitute(
        ClientNoteId $id,
        ClinicId $clinicId,
        ClientId $clientId,
        ClientNoteKind $kind,
        string $content,
        string $authorName,
        \DateTimeImmutable $createdAt,
    ): self {
        $content    = trim($content);
        $authorName = trim($authorName);

        if ('' === $content) {
            throw new \InvalidArgumentException('A client note cannot be empty.');
        }

        if (mb_strlen($content) > self::MAX_CONTENT_LENGTH) {
            throw new \InvalidArgumentException(
                \sprintf('A client note cannot exceed %d characters.', self::MAX_CONTENT_LENGTH),
            );
        }

        if ('' === $authorName) {
            throw new \InvalidArgumentException('A client note needs an author.');
        }

        $note             = new self();
        $note->id         = $id;
        $note->clinicId   = $clinicId;
        $note->clientId   = $clientId;
        $note->kind       = $kind;
        $note->content    = $content;
        $note->authorName = $authorName;
        $note->createdAt  = $createdAt;

        return $note;
    }

    public function id(): ClientNoteId
    {
        return $this->id;
    }

    public function clinicId(): ClinicId
    {
        return $this->clinicId;
    }

    public function clientId(): ClientId
    {
        return $this->clientId;
    }

    public function kind(): ClientNoteKind
    {
        return $this->kind;
    }

    public function content(): string
    {
        return $this->content;
    }

    public function authorName(): string
    {
        return $this->authorName;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
