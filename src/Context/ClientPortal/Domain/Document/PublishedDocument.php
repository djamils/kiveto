<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Document;

use App\Context\ClientPortal\Domain\Event\DocumentPublished;
use App\Context\ClientPortal\Domain\Event\DocumentWithdrawn;
use App\Shared\Domain\Aggregate\AggregateRoot;

/**
 * A clinic document made visible to one client's Portal accounts: an
 * invoice, a prescription or a shared consultation report. One row per
 * (clinic, type, source); the file is versioned so that a re-share keeps
 * serving the previous version until the new one is stored.
 */
final class PublishedDocument extends AggregateRoot
{
    public const int MAX_RENDER_ATTEMPTS = 5;

    private PublishedDocumentId $id;
    private string $clinicId;
    private ?string $clientId;
    private DocumentType $type;
    private string $sourceRef;
    private ?string $consultationId;
    private ?string $patientId;
    /** @var list<string> */
    private array $petIds;
    private string $title;
    private \DateTimeImmutable $issuedAt;
    private ?string $practitionerName;
    private ?string $storageKey;
    private int $fileVersion;
    private ?int $pendingFileVersion;
    private int $renderAttempts;
    private PublicationStatus $status;
    private \DateTimeImmutable $publishedAt;
    private \DateTimeImmutable $updatedAt;

    private function __construct()
    {
    }

    /**
     * A new publication; its first file is requested at once when the client
     * already has a confirmed link, else it waits for one.
     *
     * @param list<string> $petIds
     */
    public static function publish(
        PublishedDocumentId $id,
        string $clinicId,
        string $clientId,
        DocumentType $type,
        string $sourceRef,
        ?string $consultationId,
        ?string $patientId,
        array $petIds,
        string $title,
        \DateTimeImmutable $issuedAt,
        ?string $practitionerName,
        bool $linked,
        \DateTimeImmutable $now,
    ): self {
        $document                     = new self();
        $document->id                 = $id;
        $document->clinicId           = $clinicId;
        $document->clientId           = $clientId;
        $document->type               = $type;
        $document->sourceRef          = $sourceRef;
        $document->consultationId     = $consultationId;
        $document->patientId          = $patientId;
        $document->petIds             = $petIds;
        $document->title              = $title;
        $document->issuedAt           = $issuedAt;
        $document->practitionerName   = $practitionerName;
        $document->storageKey         = null;
        $document->fileVersion        = 0;
        $document->pendingFileVersion = $linked ? 1 : null;
        $document->renderAttempts     = 0;
        $document->status             = $linked ? PublicationStatus::PENDING_FILE : PublicationStatus::AWAITING_LINK;
        $document->publishedAt        = $now;
        $document->updatedAt          = $now;

        $document->recordDomainEvent(new DocumentPublished($id->toString(), $clinicId, $clientId, $type->value));

        return $document;
    }

    /**
     * A withdrawal that arrived before the publication: the row holds the
     * unique key so that the late publication is ignored.
     */
    public static function tombstone(
        PublishedDocumentId $id,
        string $clinicId,
        DocumentType $type,
        string $sourceRef,
        \DateTimeImmutable $now,
    ): self {
        $document                     = new self();
        $document->id                 = $id;
        $document->clinicId           = $clinicId;
        $document->clientId           = null;
        $document->type               = $type;
        $document->sourceRef          = $sourceRef;
        $document->consultationId     = null;
        $document->patientId          = null;
        $document->petIds             = [];
        $document->title              = '';
        $document->issuedAt           = $now;
        $document->practitionerName   = null;
        $document->storageKey         = null;
        $document->fileVersion        = 0;
        $document->pendingFileVersion = null;
        $document->renderAttempts     = 0;
        $document->status             = PublicationStatus::WITHDRAWN;
        $document->publishedAt        = $now;
        $document->updatedAt          = $now;

        return $document;
    }

    /**
     * @param list<string> $petIds
     */
    public static function reconstitute(
        PublishedDocumentId $id,
        string $clinicId,
        ?string $clientId,
        DocumentType $type,
        string $sourceRef,
        ?string $consultationId,
        ?string $patientId,
        array $petIds,
        string $title,
        \DateTimeImmutable $issuedAt,
        ?string $practitionerName,
        ?string $storageKey,
        int $fileVersion,
        ?int $pendingFileVersion,
        int $renderAttempts,
        PublicationStatus $status,
        \DateTimeImmutable $publishedAt,
        \DateTimeImmutable $updatedAt,
    ): self {
        $document                     = new self();
        $document->id                 = $id;
        $document->clinicId           = $clinicId;
        $document->clientId           = $clientId;
        $document->type               = $type;
        $document->sourceRef          = $sourceRef;
        $document->consultationId     = $consultationId;
        $document->patientId          = $patientId;
        $document->petIds             = $petIds;
        $document->title              = $title;
        $document->issuedAt           = $issuedAt;
        $document->practitionerName   = $practitionerName;
        $document->storageKey         = $storageKey;
        $document->fileVersion        = $fileVersion;
        $document->pendingFileVersion = $pendingFileVersion;
        $document->renderAttempts     = $renderAttempts;
        $document->status             = $status;
        $document->publishedAt        = $publishedAt;
        $document->updatedAt          = $updatedAt;

        return $document;
    }

    /**
     * The client now has a confirmed link: the awaited file is requested.
     * Returns the version to render, or null when nothing was awaiting.
     */
    public function linkConfirmed(\DateTimeImmutable $now): ?int
    {
        if (PublicationStatus::AWAITING_LINK !== $this->status) {
            return null;
        }

        return $this->requestFile($now);
    }

    /**
     * A report shared again (an addendum, a new owner): the addressee and the
     * practitioner snapshot are refreshed and a new file version is expected,
     * the previous one being served until then. Returns the version to
     * render, or null while the addressee has no confirmed link or when a
     * file for the same addressee is already expected.
     */
    public function reshare(string $clientId, ?string $practitionerName, bool $linked, \DateTimeImmutable $now): ?int
    {
        $this->assertNotWithdrawn();

        // A file is already being made for this addressee: it renders the
        // consultation as it is when it runs, so a second click adds nothing.
        if ($linked && PublicationStatus::PENDING_FILE === $this->status && $clientId === $this->clientId && $practitionerName === $this->practitionerName) {
            return null;
        }

        $this->clientId         = $clientId;
        $this->practitionerName = $practitionerName;
        $this->updatedAt        = $now;

        if (!$linked) {
            $this->status             = PublicationStatus::AWAITING_LINK;
            $this->pendingFileVersion = null;

            return null;
        }

        return $this->requestFile($now);
    }

    /**
     * Attaches the stored file of a version. Ignored (null) unless it is the
     * expected version of a document still published, so a late or
     * out-of-order render never wins. Returns the key of the file it
     * replaces, to delete later, when that key differs.
     */
    public function attachFile(int $version, string $storageKey, \DateTimeImmutable $now): ?string
    {
        if (!$this->expectsFile($version)) {
            return null;
        }

        $previous = $this->storageKey;

        $this->storageKey         = $storageKey;
        $this->fileVersion        = $version;
        $this->pendingFileVersion = null;
        $this->renderAttempts     = 0;
        $this->status             = PublicationStatus::AVAILABLE;
        $this->updatedAt          = $now;

        return null !== $previous && $previous !== $storageKey ? $previous : null;
    }

    /**
     * One more failed render of the expected file. Returns true when the
     * document has just given up (`failed`), which callers log.
     */
    public function renderFailed(\DateTimeImmutable $now): bool
    {
        if (PublicationStatus::PENDING_FILE !== $this->status) {
            return false;
        }

        ++$this->renderAttempts;
        $this->updatedAt = $now;

        if ($this->renderAttempts < self::MAX_RENDER_ATTEMPTS) {
            return false;
        }

        $this->status             = PublicationStatus::FAILED;
        $this->pendingFileVersion = null;

        return true;
    }

    /**
     * The expected file is asked for again (reconciliation): returns the
     * version to render, or null when no file is expected any more.
     */
    public function requeueFile(\DateTimeImmutable $now): ?int
    {
        if (PublicationStatus::PENDING_FILE !== $this->status) {
            return null;
        }

        $this->updatedAt = $now;

        return $this->pendingFileVersion;
    }

    /** Cancelled at the source; a withdrawn document never comes back. */
    public function withdraw(\DateTimeImmutable $now): void
    {
        if (PublicationStatus::WITHDRAWN === $this->status) {
            return;
        }

        $this->status             = PublicationStatus::WITHDRAWN;
        $this->pendingFileVersion = null;
        $this->updatedAt          = $now;

        $this->recordDomainEvent(new DocumentWithdrawn($this->id->toString(), $this->clinicId, $this->type->value));
    }

    /**
     * @param list<string> $petIds
     */
    public function assignPets(array $petIds, \DateTimeImmutable $now): void
    {
        $this->petIds    = array_values(array_unique($petIds));
        $this->updatedAt = $now;
    }

    /** The patient was reconciled into another one (same animal). */
    public function repointPatient(string $patientId, \DateTimeImmutable $now): void
    {
        $this->patientId = $patientId;
        $this->updatedAt = $now;
    }

    /** Whether a stored file of that version would be attached now. */
    public function expectsFile(int $version): bool
    {
        return PublicationStatus::WITHDRAWN !== $this->status && $version === $this->pendingFileVersion;
    }

    public function isWithdrawn(): bool
    {
        return PublicationStatus::WITHDRAWN === $this->status;
    }

    public function id(): PublishedDocumentId
    {
        return $this->id;
    }

    public function clinicId(): string
    {
        return $this->clinicId;
    }

    public function clientId(): ?string
    {
        return $this->clientId;
    }

    public function type(): DocumentType
    {
        return $this->type;
    }

    public function sourceRef(): string
    {
        return $this->sourceRef;
    }

    public function consultationId(): ?string
    {
        return $this->consultationId;
    }

    public function patientId(): ?string
    {
        return $this->patientId;
    }

    /** @return list<string> */
    public function petIds(): array
    {
        return $this->petIds;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function issuedAt(): \DateTimeImmutable
    {
        return $this->issuedAt;
    }

    public function practitionerName(): ?string
    {
        return $this->practitionerName;
    }

    public function storageKey(): ?string
    {
        return $this->storageKey;
    }

    public function fileVersion(): int
    {
        return $this->fileVersion;
    }

    public function pendingFileVersion(): ?int
    {
        return $this->pendingFileVersion;
    }

    public function renderAttempts(): int
    {
        return $this->renderAttempts;
    }

    public function status(): PublicationStatus
    {
        return $this->status;
    }

    public function publishedAt(): \DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * Always a version above any render still in flight, so that a share
     * made while a render runs is never satisfied by that older render.
     */
    private function requestFile(\DateTimeImmutable $now): int
    {
        $this->pendingFileVersion = max($this->fileVersion, $this->pendingFileVersion ?? 0) + 1;
        $this->renderAttempts     = 0;
        $this->status             = PublicationStatus::PENDING_FILE;
        $this->updatedAt          = $now;

        return $this->pendingFileVersion;
    }

    private function assertNotWithdrawn(): void
    {
        if (PublicationStatus::WITHDRAWN === $this->status) {
            throw new \LogicException('A withdrawn document cannot be shared again.');
        }
    }
}
