<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\ClientPortal\Application\Port\DocumentPosition;
use App\Context\ClientPortal\Application\Port\ListedDocument;
use App\Context\ClientPortal\Domain\Document\DocumentType as T;
use App\Context\ClientPortal\Domain\Document\PublicationStatus as S;
use App\Context\ClientPortal\Domain\Document\PublishedDocument;
use App\Context\ClientPortal\Domain\Document\PublishedDocumentId;
use App\Context\ClientPortal\Domain\Link\ClientLink;
use App\Context\ClientPortal\Domain\Link\ClientLinkId;
use App\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Repository\DbalClientLinkRepository;
use App\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Repository\DbalDocumentReadRepository;
use App\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Repository\DbalPublishedDocumentReadModel;
use App\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Repository\DbalPublishedDocumentRepository;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DbalPublishedDocumentReadModelTest extends KernelTestCase
{
    private const string USER    = '01960000-0000-7000-8000-0000000fa001';
    private const string USER2   = '01960000-0000-7000-8000-0000000fa002';
    private const string CLINIC  = '01960000-0000-7000-8000-0000000fb001';
    private const string CLINIC2 = '01960000-0000-7000-8000-0000000fb002';
    private const string CLIENT  = '01960000-0000-7000-8000-0000000fc001';
    private const string REVOKED = '01960000-0000-7000-8000-0000000fc002';
    private const string CLIENT3 = '01960000-0000-7000-8000-0000000fc003';
    private const string NOBODY  = '01960000-0000-7000-8000-0000000fc004';
    private const string PET1    = '01960000-0000-7000-8000-0000000fd001';
    private const string PET2    = '01960000-0000-7000-8000-0000000fd002';

    // Listable rows of CLIENT in CLINIC, newest first: C, B (same date, lower id), A, J.
    private const string A = '01960000-0000-7000-8000-0000000fe00a';
    private const string B = '01960000-0000-7000-8000-0000000fe00b';
    private const string C = '01960000-0000-7000-8000-0000000fe00c';
    private const string J = '01960000-0000-7000-8000-0000000fe001';
    // Never listed in CLINIC for USER.
    private const string D = '01960000-0000-7000-8000-0000000fe00d';
    private const string E = '01960000-0000-7000-8000-0000000fe00e';
    private const string F = '01960000-0000-7000-8000-0000000fe00f';
    private const string G = '01960000-0000-7000-8000-0000000fe010';
    private const string H = '01960000-0000-7000-8000-0000000fe011';
    private const string I = '01960000-0000-7000-8000-0000000fe012';

    private DbalPublishedDocumentReadModel $model;

    protected function setUp(): void
    {
        $connection = self::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);
        $this->model = new DbalPublishedDocumentReadModel($connection);

        $now   = new \DateTimeImmutable('2026-10-29 09:00', new \DateTimeZone('UTC'));
        $links = new DbalClientLinkRepository($connection);
        $links->save(self::link('f1', self::USER, self::CLINIC, self::CLIENT, $now));
        $revoked = self::link('f2', self::USER, self::CLINIC, self::REVOKED, $now);
        $revoked->revoke($now);
        $links->save($revoked);
        $links->save(self::link('f3', self::USER, self::CLINIC2, self::CLIENT3, $now));
        $links->save(self::link('f4', self::USER2, self::CLINIC, self::CLIENT, $now));

        $documents = new DbalPublishedDocumentRepository($connection);
        $at        = '2026-10-20 10:00';
        $pets      = [self::PET1, self::PET2];
        $documents->save(self::document(self::A, T::INVOICE, S::AVAILABLE, 1, '2026-10-10 10:00', [$pets[0]], 'a.pdf'));
        $documents->save(self::document(self::B, T::PRESCRIPTION, S::PENDING_FILE, 1, '2026-10-12 10:00', [$pets[1]]));
        $documents->save(self::document(self::C, T::REPORT, S::FAILED, 2, '2026-10-12 10:00', $pets));
        $documents->save(self::document(self::J, T::INVOICE, S::AVAILABLE, 2, '2026-10-05 10:00', [], 'billing/j.pdf'));
        $documents->save(self::document(self::D, T::REPORT, S::PENDING_FILE, 0, $at));
        $documents->save(self::document(self::E, T::REPORT, S::AWAITING_LINK, 1, $at));
        $documents->save(self::document(self::F, T::REPORT, S::WITHDRAWN, 1, $at));
        $documents->save(self::document(self::G, T::INVOICE, S::AVAILABLE, 1, $at, client: self::REVOKED));
        $h = self::document(self::H, T::INVOICE, S::AVAILABLE, 1, $at, client: self::CLIENT3, clinic: self::CLINIC2);
        $documents->save($h);
        $documents->save(self::document(self::I, T::INVOICE, S::AVAILABLE, 1, $at, client: self::NOBODY));

        new DbalDocumentReadRepository($connection)->markRead(self::A, self::USER, $now);
    }

    public function testTheListableDocumentsOfTheConfirmedLinksOfTheClinicNewestFirst(): void
    {
        self::assertSame([self::C, self::B, self::A, self::J], $this->ids());
        self::assertSame([self::C, self::B], $this->ids(limit: 2));
        self::assertSame([self::C], $this->ids(limit: 0), 'at least one');
        self::assertSame([self::H], $this->ids(clinic: self::CLINIC2));
        self::assertSame([], $this->ids(user: self::USER2, clinic: self::CLINIC2), 'no link there');
    }

    public function testEveryFieldOfAListedDocument(): void
    {
        [$c, , $a] = $this->model->listable(self::USER, self::CLINIC, null, null, null, 10);

        self::assertSame(
            [
                self::C, 'report', self::source(self::C), 'Document ' . self::C, [self::PET1, self::PET2],
                '2026-10-12 10:00', 'UTC', true, 2, 'client_portal/documents/' . self::C . '-v2.pdf', 'Dr Durand',
            ],
            [
                $c->id, $c->type, $c->sourceRef, $c->title, $c->petIds, $c->issuedAt->format('Y-m-d H:i'),
                $c->issuedAt->getTimezone()->getName(), $c->unread, $c->fileVersion, $c->storageKey,
                $c->practitionerName,
            ],
        );
        self::assertSame(self::A, $a->id);
        self::assertFalse($a->unread, 'read by this account');
        $other = $this->model->listable(self::USER2, self::CLINIC, null, null, null, 10)[2];
        self::assertSame([self::A, true], [$other->id, $other->unread], 'not by another');
    }

    public function testTypeAndPetFilters(): void
    {
        self::assertSame([self::A, self::J], $this->ids('invoice'));
        self::assertSame([self::C, self::A], $this->ids(pet: self::PET1));
        self::assertSame([self::C], $this->ids('report', self::PET2));
        self::assertSame([], $this->ids('invoice', self::PET2));
    }

    public function testThePageStartsAfterTheGivenPosition(): void
    {
        $at = new \DateTimeImmutable('2026-10-12 12:00', new \DateTimeZone('Europe/Paris'));

        self::assertSame([self::B, self::A, self::J], $this->ids(after: new DocumentPosition($at, self::C)));
        self::assertSame([self::A, self::J], $this->ids(after: new DocumentPosition($at, self::B)));
        $afterA = new DocumentPosition($at->modify('-2 days'), self::A);
        self::assertSame([self::J], $this->ids('invoice', after: $afterA));
    }

    public function testOneListableDocumentByIdOrBySource(): void
    {
        self::assertSame(self::B, $this->find(self::B)?->id);
        $read = $this->find(self::A);
        self::assertNotNull($read);
        self::assertFalse($read->unread);
        self::assertNull($this->find(self::D), 'never served');
        self::assertNull($this->find(self::E), 'awaiting a link');
        self::assertNull($this->find(self::F), 'withdrawn');
        self::assertNull($this->find(self::G), 'revoked link');
        self::assertNull($this->find(self::I), 'no link');
        self::assertNull($this->model->find(self::USER, self::CLINIC2, self::A), 'another clinic');

        $model        = $this->model;
        $prescription = $model->findBySource(self::USER, self::CLINIC, 'prescription', self::source(self::B));
        self::assertSame(self::B, $prescription?->id);
        self::assertNull($model->findBySource(self::USER, self::CLINIC, 'invoice', self::source(self::B)));
        self::assertNull($model->findBySource(self::USER2, self::CLINIC2, 'invoice', self::source(self::H)));
    }

    public function testASourceWithdrawnSinceWasStillPublishedToTheAccount(): void
    {
        self::assertTrue($this->model->wasPublished(self::USER, self::CLINIC, 'report', self::source(self::F)), 'withdrawn');
        self::assertTrue($this->model->wasPublished(self::USER, self::CLINIC, 'prescription', self::source(self::B)));
        self::assertFalse($this->model->wasPublished(self::USER, self::CLINIC, 'invoice', self::source(self::G)), 'revoked link');
        self::assertFalse($this->model->wasPublished(self::USER, self::CLINIC, 'invoice', self::source(self::I)), 'no link');
        self::assertFalse($this->model->wasPublished(self::USER, self::CLINIC, 'report', self::source(self::B)), 'another type');
    }

    public function testTheKeyOfAServedVersion(): void
    {
        $prefix = 'client_portal/documents/';

        self::assertSame($prefix . self::C . '-v2.pdf', $this->key(self::C, 2));
        self::assertSame($prefix . self::C . '-v1.pdf', $this->key(self::C, 1), 'an earlier report version');
        self::assertNull($this->key(self::C, 3), 'not stored yet');
        self::assertSame('billing/j.pdf', $this->key(self::J, 2));
        self::assertNull($this->key(self::J, 1), 'an invoice has one durable file');
        self::assertNull($this->key(self::F, 1), 'withdrawn');
        self::assertNull($this->key(self::D, 1), 'never served');
        self::assertNull($this->key(self::E, 1), 'awaiting a link');
        self::assertNull($this->model->versionKey(self::CLINIC2, self::C, 2), 'another clinic');
        self::assertSame($prefix . self::G . '-v1.pdf', $this->key(self::G, 1), 'no account scope');
    }

    public function testTheUnreadListableDocumentsPerClinic(): void
    {
        $unread = $this->model->unreadPerClinic(self::USER);
        $counts = array_map(\count(...), $unread);
        ksort($counts);

        self::assertSame([self::CLINIC => 3, self::CLINIC2 => 1], $counts);
        self::assertTrue($unread[self::CLINIC][0]->unread);
        self::assertSame([self::CLINIC => 4], array_map(\count(...), $this->model->unreadPerClinic(self::USER2)));
        self::assertSame([], $this->model->unreadPerClinic(self::id('99')));
    }

    public function testWhenAReportWasLastShared(): void
    {
        self::assertSame('2026-10-28 08:00', $this->sharedAt(self::CLINIC, self::C)?->format('Y-m-d H:i'));
        self::assertNotNull($this->sharedAt(self::CLINIC, self::E), 'awaiting a link');
        self::assertNull($this->sharedAt(self::CLINIC, self::F), 'withdrawn');
        self::assertNull($this->sharedAt(self::CLINIC2, self::C), 'another clinic');
        self::assertNull($this->sharedAt(self::CLINIC, self::A), 'not a report');
    }

    /**
     * @return list<string>
     */
    private function ids(
        ?string $type = null,
        ?string $pet = null,
        ?DocumentPosition $after = null,
        int $limit = 10,
        string $user = self::USER,
        string $clinic = self::CLINIC,
    ): array {
        return array_map(
            static fn (ListedDocument $d): string => $d->id,
            $this->model->listable($user, $clinic, $type, $pet, $after, $limit),
        );
    }

    private function find(string $documentId): ?ListedDocument
    {
        return $this->model->find(self::USER, self::CLINIC, $documentId);
    }

    private function key(string $documentId, int $version): ?string
    {
        return $this->model->versionKey(self::CLINIC, $documentId, $version);
    }

    private function sharedAt(string $clinicId, string $documentId): ?\DateTimeImmutable
    {
        return $this->model->reportSharedAt($clinicId, self::source($documentId));
    }

    private static function link(
        string $id,
        string $user,
        string $clinic,
        string $client,
        \DateTimeImmutable $now,
    ): ClientLink {
        return ClientLink::confirm(ClientLinkId::fromString(self::id($id)), $user, $clinic, $client, $now);
    }

    private static function id(string $suffix): string
    {
        return '01960000-0000-7000-8000-0000000ff0' . $suffix;
    }

    /** The source of a document: its id with another prefix. */
    private static function source(string $documentId): string
    {
        return '01960000-0000-7000-8000-0000000ef' . substr($documentId, -3);
    }

    /**
     * @param list<string> $petIds
     */
    private static function document(
        string $id,
        T $type,
        S $status,
        int $fileVersion,
        string $issuedAt,
        array $petIds = [],
        ?string $storageKey = null,
        string $client = self::CLIENT,
        string $clinic = self::CLINIC,
    ): PublishedDocument {
        $utc = new \DateTimeZone('UTC');

        return PublishedDocument::reconstitute(
            PublishedDocumentId::fromString($id),
            $clinic,
            $client,
            $type,
            self::source($id),
            null,
            null,
            $petIds,
            'Document ' . $id,
            new \DateTimeImmutable($issuedAt, $utc),
            T::REPORT === $type ? 'Dr Durand' : null,
            $fileVersion >= 1 ? ($storageKey ?? 'client_portal/documents/' . $id . '-v' . $fileVersion . '.pdf') : null,
            $fileVersion,
            S::PENDING_FILE === $status ? $fileVersion + 1 : null,
            0,
            $status,
            new \DateTimeImmutable('2026-10-01 08:00', $utc),
            new \DateTimeImmutable('2026-10-29 08:00', $utc),
            new \DateTimeImmutable('2026-10-28 08:00', $utc),
        );
    }
}
