<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\ClientPortal\Concurrency;

use App\Context\ClientPortal\Application\Command\ReleaseAwaitingDocuments\ReleaseAwaitingDocuments;
use App\Context\ClientPortal\Application\Command\ReleaseAwaitingDocuments\ReleaseAwaitingDocumentsHandler;
use App\Context\ClientPortal\Application\Command\WithdrawPublishedDocument\WithdrawPublishedDocument;
use App\Context\ClientPortal\Application\Command\WithdrawPublishedDocument\WithdrawPublishedDocumentHandler;
use App\Context\ClientPortal\Domain\Document\DocumentType;
use App\Context\ClientPortal\Domain\Document\PublishedDocument;
use App\Context\ClientPortal\Domain\Document\PublishedDocumentId;
use App\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Repository\DbalPublishedDocumentRepository;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Tests\Unit\Context\ClientPortal\Support\ClientPortalFakes;
use App\Tests\Unit\Context\ClientPortal\Support\DocumentSourceFakes;
use App\Tests\Unit\Context\ClientPortal\Support\SettableClock;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * S2-01, outside the test transaction, on two real connections: a release
 * of the documents awaiting a link (the client's link was just confirmed)
 * racing the withdrawal of one of them (the invoice was cancelled) never
 * brings the withdrawn document back nor asks for its file.
 */
final class PublishedDocumentReleaseRaceTest extends KernelTestCase
{
    private const string CLINIC   = '01960000-0000-7000-8000-0000000c7e01';
    private const string CLIENT   = '01960000-0000-7000-8000-0000000c7e02';
    private const string DOCUMENT = '01960000-0000-7000-8000-0000000d7e01';
    private const string SOURCE   = '01960000-0000-7000-8000-0000000b7e01';

    private Connection $first;
    private Connection $second;
    private DocumentSourceFakes $jobs;
    private SettableClock $clock;

    protected function setUp(): void
    {
        $default = self::getContainer()->get('doctrine.dbal.default_connection');
        \assert($default instanceof Connection);

        $this->first  = DriverManager::getConnection($default->getParams());
        $this->second = DriverManager::getConnection($default->getParams());
        $this->second->executeStatement('SET SESSION innodb_lock_wait_timeout = 1');
        $this->jobs  = new DocumentSourceFakes();
        $this->clock = new SettableClock(new \DateTimeImmutable('2026-10-29 09:00:00', new \DateTimeZone('UTC')));

        $this->cleanUp();
        new DbalPublishedDocumentRepository($this->first)->save(PublishedDocument::publish(
            PublishedDocumentId::fromString(self::DOCUMENT),
            self::CLINIC,
            self::CLIENT,
            DocumentType::INVOICE,
            self::SOURCE,
            null,
            null,
            [],
            'Facture F-2026-001',
            $this->clock->now(),
            null,
            false,
            $this->clock->now(),
        ));
    }

    protected function tearDown(): void
    {
        foreach ([$this->first, $this->second] as $connection) {
            while ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
        }

        $this->cleanUp();
        $this->first->close();
        $this->second->close();

        parent::tearDown();
    }

    public function testAReleaseWhoseSnapshotPredatesTheWithdrawalLeavesItWithdrawn(): void
    {
        $this->second->beginTransaction();
        // The release's snapshot is taken before the withdrawal commits
        $this->second->fetchOne('SELECT COUNT(*) FROM ' . DbalPublishedDocumentRepository::TABLE);

        $this->first->beginTransaction();
        $this->withdraw($this->first);
        $this->first->commit();

        $this->release($this->second);
        $this->second->commit();

        self::assertSame('withdrawn', $this->committedStatus());
        self::assertSame([], $this->jobs->jobs);
    }

    public function testAReleaseWaitsForAWithdrawalInFlight(): void
    {
        $this->first->beginTransaction();
        $this->withdraw($this->first);

        $this->second->beginTransaction();

        try {
            $this->release($this->second);
            self::fail('The release should wait for the withdrawal.');
        } catch (LockWaitTimeoutException) {
            self::assertTrue($this->second->isTransactionActive());
        }

        $this->first->commit();
        $this->release($this->second);
        $this->second->commit();

        self::assertSame('withdrawn', $this->committedStatus());
        self::assertSame([], $this->jobs->jobs);
    }

    private function withdraw(Connection $connection): void
    {
        $fakes = new ClientPortalFakes();

        new WithdrawPublishedDocumentHandler(
            new DbalPublishedDocumentRepository($connection),
            $fakes,
            $this->clock,
            new DomainEventPublisher($fakes),
        )(new WithdrawPublishedDocument(self::CLINIC, DocumentType::INVOICE, self::SOURCE));
    }

    private function release(Connection $connection): void
    {
        new ReleaseAwaitingDocumentsHandler(new DbalPublishedDocumentRepository($connection), $this->jobs, $this->clock)(
            new ReleaseAwaitingDocuments(self::CLINIC, self::CLIENT),
        );
    }

    private function committedStatus(): mixed
    {
        $fresh = DriverManager::getConnection($this->first->getParams());

        try {
            return $fresh->fetchOne(
                'SELECT status FROM ' . DbalPublishedDocumentRepository::TABLE . ' WHERE id = ?',
                [Uuid::fromString(self::DOCUMENT)->toBinary()],
            );
        } finally {
            $fresh->close();
        }
    }

    private function cleanUp(): void
    {
        $this->first->executeStatement(
            'DELETE FROM ' . DbalPublishedDocumentRepository::TABLE . ' WHERE clinic_id = ?',
            [Uuid::fromString(self::CLINIC)->toBinary()],
        );
    }
}
