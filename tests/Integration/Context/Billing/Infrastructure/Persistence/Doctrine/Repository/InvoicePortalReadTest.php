<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Billing\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Billing\Application\Port\InvoiceReadRepositoryInterface;
use App\Context\Billing\Application\Query\ListInvoicePortalStatuses\InvoicePortalStatus;
use App\Context\Billing\Application\Query\ListPortalPublishableInvoices\PortalPublishableInvoice;
use App\Context\Billing\Domain\Invoice;
use App\Context\Billing\Domain\Repository\InvoiceRepositoryInterface;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Context\Billing\Domain\ValueObject\InvoiceId;
use App\Context\Billing\Domain\ValueObject\InvoiceLine;
use App\Context\Billing\Domain\ValueObject\InvoiceLineId;
use App\Context\Billing\Domain\ValueObject\InvoiceLineSource;
use App\Context\Billing\Domain\ValueObject\InvoiceNumber;
use App\Context\Billing\Domain\ValueObject\OperationCategory;
use App\Context\Billing\Domain\ValueObject\Payer;
use App\Context\Billing\Domain\ValueObject\PayerId;
use App\Context\Billing\Domain\ValueObject\PriceMode;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use App\Tests\Support\Billing\SellerIdentities;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * What the Client Portal reads of Billing against a real database: the live
 * status of a batch of invoices, and the invoices its go-live import walks.
 */
final class InvoicePortalReadTest extends KernelTestCase
{
    private const string CLINIC_ID    = '01960000-0000-7000-8000-00000000c301';
    private const string OTHER_CLINIC = '01960000-0000-7000-8000-00000000c302';
    private const string CLIENT_ID    = '01960000-0000-7000-8000-00000000c304';

    // Far enough ahead that no demo invoice falls in the import window.
    private const string IMPORT_FROM = '2099-01-01 00:00:00';

    private InvoiceRepositoryInterface $invoices;
    private InvoiceReadRepositoryInterface $read;
    private UuidGeneratorInterface $uuids;
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $c = self::getContainer();

        $this->invoices = self::service(
            $c->get(InvoiceRepositoryInterface::class),
            InvoiceRepositoryInterface::class,
        );
        $this->read = self::service(
            $c->get(InvoiceReadRepositoryInterface::class),
            InvoiceReadRepositoryInterface::class,
        );
        $this->uuids      = self::service($c->get(UuidGeneratorInterface::class), UuidGeneratorInterface::class);
        $this->connection = self::service($c->get(Connection::class), Connection::class);
    }

    public function testStatusesComeBackForTheClinicsOwnInvoicesOnly(): void
    {
        $issued  = $this->issued(self::CLINIC_ID, 1);
        $paid    = $this->issued(self::CLINIC_ID, 2);
        $foreign = $this->issued(self::OTHER_CLINIC, 3);
        $this->setColumns($paid, ['status' => 'PAID']);

        $rows = $this->read->portalStatuses(ClinicId::fromString(self::CLINIC_ID), [
            $issued->id(),
            $paid->id(),
            $foreign->id(),
            InvoiceId::fromString($this->uuids->generate()),
        ]);

        $byId = [];
        foreach ($rows as $row) {
            $byId[$row->invoiceId] = $row;
        }

        self::assertCount(2, $rows);
        $first = $byId[$issued->id()->toString()];
        self::assertInstanceOf(InvoicePortalStatus::class, $first);
        self::assertSame('ISSUED', $first->status);
        self::assertSame(7200, $first->totalGrossMinorUnits);
        self::assertSame('EUR', $first->currency);
        self::assertSame('PAID', $byId[$paid->id()->toString()]->status);
    }

    public function testNoIdsGiveNoStatuses(): void
    {
        self::assertSame([], $this->read->portalStatuses(ClinicId::fromString(self::CLINIC_ID), []));
    }

    public function testTheImportWalksIssuedInvoicesWithAClientPayerByIdAcrossClinics(): void
    {
        $issued   = $this->issued(self::CLINIC_ID, 11, '2099-02-01 10:00:00');
        $paid     = $this->issued(self::OTHER_CLINIC, 12, '2099-02-01 10:00:00', 'PAID');
        $partly   = $this->issued(self::CLINIC_ID, 13, '2099-01-01 00:00:00', 'PARTIALLY_PAID');
        $credited = $this->issued(self::CLINIC_ID, 14, '2099-03-01 10:00:00', 'CREDITED');
        $this->issued(self::CLINIC_ID, 15, '2099-02-01 10:00:00', 'CANCELLED');
        $this->issued(self::CLINIC_ID, 16, '2099-02-01 10:00:00', 'DRAFT');
        $this->issued(self::CLINIC_ID, 17, '2098-12-31 23:59:59');
        $counter = $this->issued(self::CLINIC_ID, 18, '2099-02-01 10:00:00');
        $this->setColumns($counter, ['payer_client_id' => null]);

        $expected = [
            new PortalPublishableInvoice($issued->id()->toString(), self::CLINIC_ID),
            new PortalPublishableInvoice($paid->id()->toString(), self::OTHER_CLINIC),
            new PortalPublishableInvoice($partly->id()->toString(), self::CLINIC_ID),
            new PortalPublishableInvoice($credited->id()->toString(), self::CLINIC_ID),
        ];
        usort($expected, static fn (PortalPublishableInvoice $a, PortalPublishableInvoice $b): int => strcmp(
            Uuid::fromString($a->invoiceId)->toBinary(),
            Uuid::fromString($b->invoiceId)->toBinary(),
        ));

        $from = new \DateTimeImmutable(self::IMPORT_FROM);

        self::assertSame(self::pairs($expected), self::pairs($this->read->portalPublishable($from, null, 10)));

        $first = $this->read->portalPublishable($from, null, 2);
        self::assertSame(self::pairs(\array_slice($expected, 0, 2)), self::pairs($first));

        $next = $this->read->portalPublishable($from, InvoiceId::fromString($first[1]->invoiceId), 2);
        self::assertSame(self::pairs(\array_slice($expected, 2, 2)), self::pairs($next));

        self::assertSame([], $this->read->portalPublishable($from, InvoiceId::fromString($next[1]->invoiceId), 2));
    }

    /**
     * @param list<PortalPublishableInvoice> $invoices
     *
     * @return list<array{string, string}>
     */
    private static function pairs(array $invoices): array
    {
        return array_map(
            static fn (PortalPublishableInvoice $invoice): array => [$invoice->invoiceId, $invoice->clinicId],
            $invoices,
        );
    }

    /**
     * @param array<string, string|null> $columns
     */
    private function setColumns(Invoice $invoice, array $columns): void
    {
        foreach ($columns as $column => $value) {
            $this->connection->executeStatement(
                \sprintf('UPDATE billing__invoices SET %s = :value WHERE id = :id', $column),
                ['value' => $value, 'id' => Uuid::fromString($invoice->id()->toString())->toBinary()],
            );
        }
    }

    private function issued(string $clinicId, int $sequence, ?string $issuedAt = null, ?string $status = null): Invoice
    {
        $now     = new \DateTimeImmutable('2026-08-22 10:00:00');
        $invoice = Invoice::draft(
            id: InvoiceId::fromString($this->uuids->generate()),
            clinicId: ClinicId::fromString($clinicId),
            admissionId: null,
            payer: Payer::create(PayerId::fromString(self::CLIENT_ID), 'Sophie Dupont', ['12 rue des Lilas']),
            currency: CurrencyCode::fromString('EUR'),
            now: $now,
        );

        $invoice->addLine($this->line(), $now);
        $invoice->issue(InvoiceNumber::of(2026, $sequence), SellerIdentities::complete(), $now);
        $this->invoices->save($invoice);

        $columns = [];
        if (null !== $issuedAt) {
            $columns['issued_at'] = $issuedAt;
        }
        if (null !== $status) {
            $columns['status'] = $status;
        }
        $this->setColumns($invoice, $columns);

        return $invoice;
    }

    private function line(): InvoiceLine
    {
        $eur = CurrencyCode::fromString('EUR');

        return InvoiceLine::create(
            id: InvoiceLineId::fromString($this->uuids->generate()),
            label: 'Consultation standard',
            code: 'CONS_STD',
            quantity: '1',
            enteredUnitPrice: Money::fromMinorUnits(7200, $eur),
            priceMode: PriceMode::INCLUDING_TAX,
            taxCategoryCode: 'veterinary.act.consultation',
            taxRatePercent: '20',
            operationCategory: OperationCategory::SERVICES,
            source: InvoiceLineSource::CONSULTATION,
            sourceReference: 'src-1',
        );
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private static function service(mixed $service, string $class): object
    {
        \assert($service instanceof $class);

        return $service;
    }
}
