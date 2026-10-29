<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Billing\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Billing\Application\Command\EnsureSentInvoiceFile\SentDocumentFile;
use App\Context\Billing\Domain\Dispatch\SentDocumentKind;
use App\Context\Billing\Infrastructure\Persistence\Doctrine\Repository\DbalSentDocumentFileStore;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Two first sends of the same invoice, on two real connections (outside the
 * test transaction): the second one looked before the first committed, its
 * insert is ignored, and it still returns the row the first one recorded —
 * its re-read is a locking read, not its REPEATABLE READ snapshot.
 */
final class SentDocumentFileLockTest extends KernelTestCase
{
    private const string CLINIC  = '01960000-0000-7000-8000-00000000d301';
    private const string INVOICE = '01960000-0000-7000-8000-00000000d302';

    private Connection $first;
    private Connection $second;

    protected function setUp(): void
    {
        $default = self::getContainer()->get('doctrine.dbal.default_connection');
        \assert($default instanceof Connection);

        $this->first  = DriverManager::getConnection($default->getParams());
        $this->second = DriverManager::getConnection($default->getParams());
        $this->cleanUp();
    }

    protected function tearDown(): void
    {
        foreach ([$this->first, $this->second] as $connection) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
        }

        $this->cleanUp();
        $this->first->close();
        $this->second->close();

        parent::tearDown();
    }

    public function testTheLoserReturnsTheWinnersFile(): void
    {
        $at = new \DateTimeImmutable('2026-10-26 10:00:00');

        $this->second->beginTransaction();
        self::assertNull(new DbalSentDocumentFileStore($this->second)->find(self::CLINIC, self::INVOICE));

        $this->first->beginTransaction();
        new DbalSentDocumentFileStore($this->first)->record(self::CLINIC, SentDocumentKind::INVOICE, self::INVOICE, new SentDocumentFile('billing/sent/invoice/x.pdf', 'FAC-WIN.pdf'), $at);
        $this->first->commit();

        $file = new DbalSentDocumentFileStore($this->second)->record(self::CLINIC, SentDocumentKind::INVOICE, self::INVOICE, new SentDocumentFile('billing/sent/invoice/x.pdf', 'FAC-LOSE.pdf'), $at);
        $this->second->commit();

        self::assertSame('FAC-WIN.pdf', $file->filename);
    }

    private function cleanUp(): void
    {
        $this->first->executeStatement('DELETE FROM billing__sent_document_files WHERE document_id = ?', [Uuid::fromString(self::INVOICE)->toBinary()]);
    }
}
