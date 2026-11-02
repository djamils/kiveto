<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\ClientPortal\Concurrency;

use App\Context\ClientPortal\Infrastructure\Lock\DbalPortalLock;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * S6-06, S7-02, outside the test transaction, on two real connections: a
 * Portal lock is held until the transaction ends, per key; the first lock
 * of a key creates its row without blocking anyone else.
 */
final class PortalLockTest extends KernelTestCase
{
    private const string USER   = '01960000-0000-7000-8000-0000000e7c01';
    private const string OTHER  = '01960000-0000-7000-8000-0000000e7c02';
    private const string CLINIC = '01960000-0000-7000-8000-0000000c7c01';
    private const string PET    = '01960000-0000-7000-8000-0000000a7c01';

    private Connection $first;
    private Connection $second;

    protected function setUp(): void
    {
        $default = self::getContainer()->get('doctrine.dbal.default_connection');
        \assert($default instanceof Connection);

        $this->first  = DriverManager::getConnection($default->getParams());
        $this->second = DriverManager::getConnection($default->getParams());
        $this->second->executeStatement('SET SESSION innodb_lock_wait_timeout = 1');

        $this->cleanUp();
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

    public function testALockIsHeldUntilTheTransactionEndsAndOnlyForItsKey(): void
    {
        $this->first->beginTransaction();
        $this->second->beginTransaction();

        new DbalPortalLock($this->first)->lockAccount(self::USER);
        new DbalPortalLock($this->first)->lockPetChanges(self::CLINIC, self::PET);

        // Other keys are free
        new DbalPortalLock($this->second)->lockAccount(self::OTHER);

        $takes = [
            static fn (DbalPortalLock $lock) => $lock->lockAccount(self::USER),
            static fn (DbalPortalLock $lock) => $lock->lockPetChanges(self::CLINIC, self::PET),
        ];

        foreach ($takes as $take) {
            try {
                $take(new DbalPortalLock($this->second));
                self::fail('The second transaction should wait for the lock.');
            } catch (LockWaitTimeoutException) {
                self::assertTrue($this->second->isTransactionActive());
            }
        }

        $this->first->commit();

        new DbalPortalLock($this->second)->lockAccount(self::USER);
        new DbalPortalLock($this->second)->lockPetChanges(self::CLINIC, self::PET);
        $this->second->commit();

        self::assertCount(3, $this->first->fetchFirstColumn('SELECT lock_key FROM ' . DbalPortalLock::TABLE . ' WHERE lock_key IN (?, ?, ?)', ['account:' . self::USER, 'account:' . self::OTHER, 'pet-changes:' . self::CLINIC . ':' . self::PET]));
    }

    private function cleanUp(): void
    {
        $this->first->executeStatement('DELETE FROM ' . DbalPortalLock::TABLE . ' WHERE lock_key IN (?, ?, ?)', ['account:' . self::USER, 'account:' . self::OTHER, 'pet-changes:' . self::CLINIC . ':' . self::PET]);
    }
}
