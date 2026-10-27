<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Billing\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Billing\Application\Command\RemindUnpaidInvoices\RemindUnpaidInvoices;
use App\Context\Billing\Application\Command\RemindUnpaidInvoices\RemindUnpaidInvoicesHandler;
use App\Context\Billing\Application\Command\SendInvoiceToClient\InvoiceDispatchOutcome;
use App\Context\Billing\Application\Dispatch\UnpaidReminderSelection;
use App\Context\Billing\Domain\Repository\CreditNoteRepositoryInterface;
use App\Context\Billing\Domain\Repository\InvoiceRepositoryInterface;
use App\Context\Billing\Infrastructure\Persistence\Doctrine\Repository\DbalInvoiceDispatchRepository;
use App\Context\Billing\Infrastructure\Persistence\Doctrine\Repository\DbalInvoiceReadRepository;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Unit\Context\Billing\Support\CapturingDispatchJobs;
use App\Tests\Unit\Context\Billing\Support\FakeClientContacts;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Two « Relancer » of the same invoice at once (double click, two desks), on
 * two real connections outside the test transaction: the second waits for
 * the first one's invoice lock, then sees its reminder — one reminder, not
 * two; reminders of two different invoices do not wait for each other.
 */
final class ReminderLockTest extends KernelTestCase
{
    private const string CLINIC  = '01960000-0000-7000-8000-00000000d401';
    private const string INVOICE = '01960000-0000-7000-8000-00000000d402';
    private const string CLIENT  = '01960000-0000-7000-8000-00000000d403';

    private const string OTHER_INVOICE = '01960000-0000-7000-8000-00000000d404';

    private Connection $first;
    private Connection $second;
    private int $sequence = 0;

    protected function setUp(): void
    {
        $default = self::getContainer()->get('doctrine.dbal.default_connection');
        \assert($default instanceof Connection);

        $this->first  = DriverManager::getConnection($default->getParams());
        $this->second = DriverManager::getConnection($default->getParams());
        $this->second->executeStatement('SET SESSION innodb_lock_wait_timeout = 1');
        $this->cleanUp();

        $this->insertInvoice(self::INVOICE, 'LCK-2026-000001');
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

    public function testASecondReminderOfTheSameInvoiceWaitsForTheFirst(): void
    {
        $this->first->beginTransaction();
        self::assertSame([self::INVOICE], $this->remind($this->first)->queued);

        // The double click: the second request blocks on the invoice instead of queuing a second reminder.
        $this->second->beginTransaction();

        try {
            $this->remind($this->second);
            self::fail('The second reminder should wait for the first one.');
        } catch (LockWaitTimeoutException) {
            $this->second->rollBack();
        }

        $this->first->commit();

        // Once the first committed, the waiting request reads past it: a transaction opened before
        // that commit sees it, its snapshot being taken after the lock.
        $this->second->beginTransaction();
        $loser = $this->remind($this->second);
        $this->second->commit();

        self::assertSame([[], [self::INVOICE => InvoiceDispatchOutcome::SKIP_REMINDED_TODAY]], [$loser->queued, $loser->skipped]);
        self::assertSame(1, $this->number('SELECT COUNT(*) FROM billing__invoice_dispatches WHERE invoice_id = ?', [Uuid::fromString(self::INVOICE)->toBinary()]));
    }

    public function testRemindersOfTwoInvoicesOfTheClinicDoNotWaitForEachOther(): void
    {
        $this->insertInvoice(self::OTHER_INVOICE, 'LCK-2026-000002');

        $this->first->beginTransaction();
        self::assertSame([self::INVOICE], $this->remind($this->first)->queued);

        $this->second->beginTransaction();
        self::assertSame([self::OTHER_INVOICE], $this->remind($this->second, self::OTHER_INVOICE)->queued);
        $this->second->commit();
        $this->first->commit();

        self::assertSame(2, $this->number('SELECT COUNT(*) FROM billing__invoice_dispatches WHERE invoice_id IN (?, ?)', [Uuid::fromString(self::INVOICE)->toBinary(), Uuid::fromString(self::OTHER_INVOICE)->toBinary()]));
    }

    private function remind(Connection $connection, string $invoiceId = self::INVOICE): InvoiceDispatchOutcome
    {
        $clock      = new FrozenClock(new \DateTimeImmutable('2026-10-26 10:00:00', new \DateTimeZone('UTC')));
        $dispatches = new DbalInvoiceDispatchRepository($connection);
        $selection  = new UnpaidReminderSelection(
            new DbalInvoiceReadRepository($connection, $this->createStub(InvoiceRepositoryInterface::class), $this->createStub(CreditNoteRepositoryInterface::class)),
            $dispatches,
            new FakeClientContacts(FakeClientContacts::contact(clientId: self::CLIENT)),
            $clock,
        );

        return new RemindUnpaidInvoicesHandler($selection, $dispatches, new CapturingDispatchJobs(), $this->uuids(), $clock)(
            new RemindUnpaidInvoices(self::CLINIC, [$invoiceId]),
        );
    }

    private function insertInvoice(string $id, string $number): void
    {
        $this->first->insert('billing__invoices', [
            'id'                  => Uuid::fromString($id)->toBinary(),
            'clinic_id'           => Uuid::fromString(self::CLINIC)->toBinary(),
            'number'              => $number,
            'status'              => 'ISSUED',
            'payer_client_id'     => Uuid::fromString(self::CLIENT)->toBinary(),
            'payer_name'          => 'Sophie Dubois',
            'payer_address_lines' => '[]',
            'currency'            => 'EUR',
            'tax_breakdown'       => '[]',
            'total_gross'         => 6450,
            'created_at'          => '2026-10-16 08:30:00',
            'updated_at'          => '2026-10-16 08:30:00',
            'issued_at'           => '2026-10-16 08:30:00',
        ]);
    }

    private function uuids(): UuidGeneratorInterface
    {
        return new class($this->sequence) implements UuidGeneratorInterface {
            public function __construct(private int &$sequence)
            {
            }

            public function generate(): string
            {
                return \sprintf('01960000-0000-7000-8000-0000000d4%03d', ++$this->sequence);
            }
        };
    }

    /**
     * @param list<string> $parameters
     */
    private function number(string $sql, array $parameters): int
    {
        $value = $this->first->fetchOne($sql, $parameters);
        \assert(is_numeric($value));

        return (int) $value;
    }

    private function cleanUp(): void
    {
        foreach ([self::INVOICE, self::OTHER_INVOICE] as $id) {
            $invoice = Uuid::fromString($id)->toBinary();
            $this->first->executeStatement('DELETE FROM billing__invoice_dispatches WHERE invoice_id = ?', [$invoice]);
            $this->first->executeStatement('DELETE FROM billing__invoices WHERE id = ?', [$invoice]);
        }
    }
}
