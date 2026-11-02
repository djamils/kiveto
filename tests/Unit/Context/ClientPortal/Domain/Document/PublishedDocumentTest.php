<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Domain\Document;

use App\Context\ClientPortal\Domain\Document\DocumentType;
use App\Context\ClientPortal\Domain\Document\PublicationStatus;
use App\Context\ClientPortal\Domain\Document\PublishedDocument;
use App\Context\ClientPortal\Domain\Document\PublishedDocumentId;
use App\Context\ClientPortal\Domain\Event\DocumentPublished;
use App\Context\ClientPortal\Domain\Event\DocumentWithdrawn;
use PHPUnit\Framework\TestCase;

final class PublishedDocumentTest extends TestCase
{
    private const string ID     = '01960000-0000-7000-8000-0000000d0c01';
    private const string CLINIC = '01960000-0000-7000-8000-0000000d0c02';
    private const string CLIENT = '01960000-0000-7000-8000-0000000d0c03';
    private const string SOURCE = '01960000-0000-7000-8000-0000000d0c04';

    public function testALinkedPublicationRequestsItsFirstFileAtOnce(): void
    {
        $document = PublishedDocument::publish(
            PublishedDocumentId::fromString(self::ID),
            self::CLINIC,
            self::CLIENT,
            DocumentType::REPORT,
            self::SOURCE,
            'consultation-1',
            'patient-1',
            ['pet-1', 'pet-2'],
            'Compte rendu',
            new \DateTimeImmutable('2026-10-30 10:00'),
            'Dr Martin',
            true,
            new \DateTimeImmutable('2026-10-31 09:00'),
        );

        self::assertSame(
            [
                self::ID, self::CLINIC, self::CLIENT, DocumentType::REPORT, self::SOURCE, 'consultation-1', 'patient-1',
                ['pet-1', 'pet-2'], 'Compte rendu', '2026-10-30 10:00', 'Dr Martin', null, 0, 1, 0,
                PublicationStatus::PENDING_FILE, '2026-10-31 09:00', '2026-10-31 09:00', '2026-10-31 09:00', false,
            ],
            [
                $document->id()->toString(), $document->clinicId(), $document->clientId(), $document->type(),
                $document->sourceRef(), $document->consultationId(), $document->patientId(), $document->petIds(),
                $document->title(), $document->issuedAt()->format('Y-m-d H:i'), $document->practitionerName(),
                $document->storageKey(), $document->fileVersion(), $document->pendingFileVersion(),
                $document->renderAttempts(), $document->status(), $document->publishedAt()->format('Y-m-d H:i'),
                $document->updatedAt()->format('Y-m-d H:i'), $document->sharedAt()->format('Y-m-d H:i'),
                $document->isWithdrawn(),
            ],
        );

        $events = $document->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(DocumentPublished::class, $events[0]);
        self::assertSame(
            ['client_portal.document.published.v1', self::ID],
            [$events[0]->name(), $events[0]->aggregateId()],
        );
        self::assertSame(
            ['documentId' => self::ID, 'clinicId' => self::CLINIC, 'clientId' => self::CLIENT, 'type' => 'report'],
            $events[0]->payload(),
        );
    }

    public function testAnUnlinkedPublicationAwaitsALink(): void
    {
        $document = $this->published(linked: false);

        self::assertSame(
            [PublicationStatus::AWAITING_LINK, null, 0],
            [$document->status(), $document->pendingFileVersion(), $document->fileVersion()],
        );
        self::assertCount(1, $document->pullDomainEvents());
    }

    public function testATombstoneIsBornWithdrawnWithoutEvent(): void
    {
        $now      = new \DateTimeImmutable('2026-10-31 09:00');
        $document = PublishedDocument::tombstone(
            PublishedDocumentId::fromString(self::ID),
            self::CLINIC,
            DocumentType::PRESCRIPTION,
            self::SOURCE,
            $now,
        );

        self::assertSame(
            [
                self::ID, self::CLINIC, null, DocumentType::PRESCRIPTION, self::SOURCE, null, null, [], '', $now, null,
                null, 0, null, 0, PublicationStatus::WITHDRAWN, $now, $now, $now, true,
            ],
            [
                $document->id()->toString(), $document->clinicId(), $document->clientId(), $document->type(),
                $document->sourceRef(), $document->consultationId(), $document->patientId(), $document->petIds(),
                $document->title(), $document->issuedAt(), $document->practitionerName(), $document->storageKey(),
                $document->fileVersion(), $document->pendingFileVersion(), $document->renderAttempts(),
                $document->status(), $document->publishedAt(), $document->updatedAt(), $document->sharedAt(),
                $document->isWithdrawn(),
            ],
        );
        self::assertSame([], $document->pullDomainEvents());

        // Withdrawing a tombstone is a no-op: no event.
        $document->withdraw(new \DateTimeImmutable('2026-11-01'));
        self::assertSame([], $document->pullDomainEvents());
        self::assertSame($now, $document->updatedAt());
    }

    public function testAConfirmedLinkRequestsTheFirstFileOfAnAwaitingDocument(): void
    {
        $document = $this->published(linked: false);
        $document->renderFailed(new \DateTimeImmutable('2026-10-31 09:30'));

        self::assertSame(1, $document->linkConfirmed(new \DateTimeImmutable('2026-11-01 08:00')));
        self::assertSame(
            [PublicationStatus::PENDING_FILE, 1, 0, '2026-11-01 08:00'],
            [
                $document->status(), $document->pendingFileVersion(), $document->renderAttempts(),
                $document->updatedAt()->format('Y-m-d H:i'),
            ],
        );

        // Already pending: nothing more to request.
        self::assertNull($document->linkConfirmed(new \DateTimeImmutable('2026-11-02 08:00')));
        self::assertSame('2026-11-01 08:00', $document->updatedAt()->format('Y-m-d H:i'));
    }

    public function testAReshareToALinkedClientExpectsTheNextVersionAndKeepsServingThePreviousOne(): void
    {
        $document = $this->published(linked: true);
        $document->attachFile(1, 'key-v1', new \DateTimeImmutable('2026-10-31 09:05'));

        self::assertSame('2026-10-31 09:00', $document->sharedAt()->format('Y-m-d H:i'), 'a file attached is not a share');

        $version = $document->reshare('client-2', 'Dr Durand', true, new \DateTimeImmutable('2026-11-03 10:00'));
        self::assertSame(2, $version);
        self::assertSame(
            ['client-2', 'Dr Durand', PublicationStatus::PENDING_FILE, 2, 1, 'key-v1', '2026-11-03 10:00', '2026-11-03 10:00'],
            [
                $document->clientId(), $document->practitionerName(), $document->status(),
                $document->pendingFileVersion(), $document->fileVersion(), $document->storageKey(),
                $document->updatedAt()->format('Y-m-d H:i'), $document->sharedAt()->format('Y-m-d H:i'),
            ],
        );

        // A second click while that file is being made adds nothing.
        self::assertNull($document->reshare('client-2', 'Dr Durand', true, new \DateTimeImmutable('2026-11-03 10:01')));
        self::assertSame([2, '2026-11-03 10:00'], [$document->pendingFileVersion(), $document->updatedAt()->format('Y-m-d H:i')]);
        $document->assignPets(['pet-2'], new \DateTimeImmutable('2026-11-04 10:00'));
        self::assertSame('2026-11-03 10:00', $document->sharedAt()->format('Y-m-d H:i'), 'nor are pets assigned');
    }

    public function testAReshareToAnUnlinkedClientAwaitsALink(): void
    {
        $document = $this->published(linked: true);
        $document->attachFile(1, 'key-v1', new \DateTimeImmutable('2026-10-31 09:05'));

        self::assertNull($document->reshare('client-2', null, false, new \DateTimeImmutable('2026-11-03 10:00')));
        self::assertSame(
            ['client-2', null, PublicationStatus::AWAITING_LINK, null, 1, 'key-v1'],
            [
                $document->clientId(), $document->practitionerName(), $document->status(),
                $document->pendingFileVersion(), $document->fileVersion(), $document->storageKey(),
            ],
        );

        // The link then asks for the version after the one served.
        self::assertSame(2, $document->linkConfirmed(new \DateTimeImmutable('2026-11-04')));
    }

    public function testAWithdrawnDocumentCannotBeSharedAgain(): void
    {
        $document = $this->published(linked: true);
        $document->withdraw(new \DateTimeImmutable('2026-11-01'));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('A withdrawn document cannot be shared again.');

        $document->reshare(self::CLIENT, null, true, new \DateTimeImmutable('2026-11-02'));
    }

    public function testTheExpectedFileMakesTheDocumentAvailable(): void
    {
        $document = $this->published(linked: true);
        $document->renderFailed(new \DateTimeImmutable('2026-10-31 09:01'));

        self::assertNull($document->attachFile(1, 'key-v1', new \DateTimeImmutable('2026-10-31 09:05')));
        self::assertSame(
            ['key-v1', 1, null, 0, PublicationStatus::AVAILABLE, '2026-10-31 09:05'],
            [
                $document->storageKey(), $document->fileVersion(), $document->pendingFileVersion(),
                $document->renderAttempts(), $document->status(), $document->updatedAt()->format('Y-m-d H:i'),
            ],
        );

        // A new version stored under another key hands back the key it replaces.
        $document->reshare(self::CLIENT, null, true, new \DateTimeImmutable('2026-11-01'));
        self::assertSame('key-v1', $document->attachFile(2, 'key-v2', new \DateTimeImmutable('2026-11-01 00:05')));
        self::assertSame(['key-v2', 2], [$document->storageKey(), $document->fileVersion()]);

        // Same key again: nothing to delete.
        $document->reshare(self::CLIENT, null, true, new \DateTimeImmutable('2026-11-02'));
        self::assertNull($document->attachFile(3, 'key-v2', new \DateTimeImmutable('2026-11-02 00:05')));
        self::assertSame(['key-v2', 3, PublicationStatus::AVAILABLE], [
            $document->storageKey(), $document->fileVersion(), $document->status(),
        ]);
    }

    public function testAnUnexpectedFileIsIgnored(): void
    {
        $document = $this->published(linked: true);
        $before   = $document->updatedAt();

        // Wrong version.
        self::assertNull($document->attachFile(2, 'key-v2', new \DateTimeImmutable('2026-11-01')));
        self::assertSame(
            [null, 0, 1, PublicationStatus::PENDING_FILE, $before],
            [
                $document->storageKey(), $document->fileVersion(), $document->pendingFileVersion(),
                $document->status(), $document->updatedAt(),
            ],
        );

        // Withdrawn.
        $document->withdraw(new \DateTimeImmutable('2026-11-02'));
        self::assertNull($document->attachFile(1, 'key-v1', new \DateTimeImmutable('2026-11-03')));
        self::assertSame([null, 0, PublicationStatus::WITHDRAWN], [
            $document->storageKey(), $document->fileVersion(), $document->status(),
        ]);
    }

    public function testAnOutOfOrderRenderNeverWins(): void
    {
        $document = $this->published(linked: true);
        $document->attachFile(1, 'key-v1', new \DateTimeImmutable('2026-10-31 09:05'));
        $document->reshare(self::CLIENT, null, true, new \DateTimeImmutable('2026-11-01'));

        // v2 lands first, then the late v1 render.
        self::assertSame('key-v1', $document->attachFile(2, 'key-v2', new \DateTimeImmutable('2026-11-01 00:05')));
        self::assertNull($document->attachFile(1, 'key-v1-late', new \DateTimeImmutable('2026-11-01 00:06')));
        self::assertSame(['key-v2', 2, PublicationStatus::AVAILABLE], [
            $document->storageKey(), $document->fileVersion(), $document->status(),
        ]);
    }

    public function testTheFifthFailedRenderGivesUp(): void
    {
        $document = $this->published(linked: true);

        for ($attempt = 1; $attempt < PublishedDocument::MAX_RENDER_ATTEMPTS; ++$attempt) {
            self::assertFalse($document->renderFailed(new \DateTimeImmutable('2026-11-01 00:0' . $attempt)));
            self::assertSame([$attempt, PublicationStatus::PENDING_FILE, 1], [
                $document->renderAttempts(), $document->status(), $document->pendingFileVersion(),
            ]);
        }
        self::assertSame('2026-11-01 00:04', $document->updatedAt()->format('Y-m-d H:i'));

        self::assertTrue($document->renderFailed(new \DateTimeImmutable('2026-11-01 00:05')));
        self::assertSame([5, PublicationStatus::FAILED, null, '2026-11-01 00:05'], [
            $document->renderAttempts(), $document->status(), $document->pendingFileVersion(),
            $document->updatedAt()->format('Y-m-d H:i'),
        ]);

        // No longer pending: further failures are not counted.
        self::assertFalse($document->renderFailed(new \DateTimeImmutable('2026-11-01 00:06')));
        self::assertSame([5, '2026-11-01 00:05'], [
            $document->renderAttempts(), $document->updatedAt()->format('Y-m-d H:i'),
        ]);
    }

    public function testAFailureOutsidePendingIsNotCounted(): void
    {
        $document = $this->published(linked: false);

        self::assertFalse($document->renderFailed(new \DateTimeImmutable('2026-11-01')));
        self::assertSame(0, $document->renderAttempts());
    }

    public function testAPendingFileIsRequeuedAtTheSameVersion(): void
    {
        $document = $this->published(linked: true);
        $document->renderFailed(new \DateTimeImmutable('2026-11-01 00:01'));

        self::assertSame(1, $document->requeueFile(new \DateTimeImmutable('2026-11-01 06:00')));
        self::assertSame([1, 1, PublicationStatus::PENDING_FILE, '2026-11-01 06:00'], [
            $document->pendingFileVersion(), $document->renderAttempts(), $document->status(),
            $document->updatedAt()->format('Y-m-d H:i'),
        ]);
    }

    public function testNothingIsRequeuedOutsidePending(): void
    {
        $awaiting = $this->published(linked: false);
        self::assertNull($awaiting->requeueFile(new \DateTimeImmutable('2026-11-01 06:00')));
        self::assertSame('2026-10-31 09:00', $awaiting->updatedAt()->format('Y-m-d H:i'));

        $available = $this->published(linked: true);
        $available->attachFile(1, 'key-v1', new \DateTimeImmutable('2026-10-31 09:05'));
        self::assertNull($available->requeueFile(new \DateTimeImmutable('2026-11-01 06:00')));
        self::assertSame('2026-10-31 09:05', $available->updatedAt()->format('Y-m-d H:i'));
    }

    public function testAWithdrawalIsRecordedOnce(): void
    {
        $document = $this->published(linked: true);
        (void) $document->pullDomainEvents();

        $document->withdraw(new \DateTimeImmutable('2026-11-01 10:00'));
        $document->withdraw(new \DateTimeImmutable('2026-11-02 10:00'));

        self::assertSame([PublicationStatus::WITHDRAWN, null, true, '2026-11-01 10:00'], [
            $document->status(), $document->pendingFileVersion(), $document->isWithdrawn(),
            $document->updatedAt()->format('Y-m-d H:i'),
        ]);

        $events = $document->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(DocumentWithdrawn::class, $events[0]);
        self::assertSame(
            ['client_portal.document.withdrawn.v1', self::ID],
            [$events[0]->name(), $events[0]->aggregateId()],
        );
        self::assertSame(
            ['documentId' => self::ID, 'clinicId' => self::CLINIC, 'type' => 'invoice'],
            $events[0]->payload(),
        );
    }

    public function testPetsAreDedupedAndThePatientRepointed(): void
    {
        $document = $this->published(linked: true);

        $document->assignPets(['pet-1', 'pet-2', 'pet-1', 'pet-3', 'pet-2'], new \DateTimeImmutable('2026-11-01'));
        self::assertSame(['pet-1', 'pet-2', 'pet-3'], $document->petIds());
        self::assertSame('2026-11-01', $document->updatedAt()->format('Y-m-d'));

        $document->repointPatient('patient-2', new \DateTimeImmutable('2026-11-02'));
        self::assertSame(['patient-2', '2026-11-02'], [
            $document->patientId(), $document->updatedAt()->format('Y-m-d'),
        ]);
    }

    public function testAReconstitutedDocumentKeepsEveryProperty(): void
    {
        $issued    = new \DateTimeImmutable('2026-10-01');
        $published = new \DateTimeImmutable('2026-10-02');
        $updated   = new \DateTimeImmutable('2026-10-03');
        $shared    = new \DateTimeImmutable('2026-10-02 12:00');

        $document = PublishedDocument::reconstitute(
            PublishedDocumentId::fromString(self::ID),
            self::CLINIC,
            null,
            DocumentType::LAB_RESULT,
            self::SOURCE,
            null,
            null,
            ['pet-1'],
            'Résultat',
            $issued,
            null,
            'key-v3',
            3,
            4,
            2,
            PublicationStatus::PENDING_FILE,
            $published,
            $updated,
            $shared,
        );

        self::assertSame(
            [
                self::ID, self::CLINIC, null, DocumentType::LAB_RESULT, self::SOURCE, null, null, ['pet-1'], 'Résultat',
                $issued, null, 'key-v3', 3, 4, 2, PublicationStatus::PENDING_FILE, $published, $updated, $shared,
            ],
            [
                $document->id()->toString(), $document->clinicId(), $document->clientId(), $document->type(),
                $document->sourceRef(), $document->consultationId(), $document->patientId(), $document->petIds(),
                $document->title(), $document->issuedAt(), $document->practitionerName(), $document->storageKey(),
                $document->fileVersion(), $document->pendingFileVersion(), $document->renderAttempts(),
                $document->status(), $document->publishedAt(), $document->updatedAt(), $document->sharedAt(),
            ],
        );
        self::assertSame([], $document->pullDomainEvents());
    }

    public function testOnlyInvoicesAreServedFromTheirSourceRatherThanThePortalStorage(): void
    {
        self::assertSame(
            [
                'invoice'      => false,
                'prescription' => true,
                'report'       => true,
                'lab_result'   => true,
                'certificate'  => true,
            ],
            array_combine(
                array_map(static fn (DocumentType $type): string => $type->value, DocumentType::cases()),
                array_map(static fn (DocumentType $type): bool => $type->hasPortalFile(), DocumentType::cases()),
            ),
        );
    }

    private function published(bool $linked): PublishedDocument
    {
        return PublishedDocument::publish(
            PublishedDocumentId::fromString(self::ID),
            self::CLINIC,
            self::CLIENT,
            DocumentType::INVOICE,
            self::SOURCE,
            null,
            'patient-1',
            ['pet-1'],
            'Facture F-2026-001',
            new \DateTimeImmutable('2026-10-30'),
            null,
            $linked,
            new \DateTimeImmutable('2026-10-31 09:00'),
        );
    }
}
