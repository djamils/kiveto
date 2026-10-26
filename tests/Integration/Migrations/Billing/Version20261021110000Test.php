<?php

declare(strict_types=1);

namespace App\Tests\Integration\Migrations\Billing;

use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use DoctrineMigrations\Billing\Version20260822000000;
use DoctrineMigrations\Billing\Version20261014200000;
use DoctrineMigrations\Billing\Version20261016100000;
use DoctrineMigrations\Billing\Version20261017120000;
use DoctrineMigrations\Billing\Version20261021110000;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Runs the Billing migrations up to the frozen amounts on a scratch database
 * holding documents issued before it: an issued invoice keeps the amounts it
 * was issued with, discount included.
 */
final class Version20261021110000Test extends KernelTestCase
{
    private const string DATABASE = 'kiveto_migration_billing';

    private Connection $scratch;

    public static function setUpBeforeClass(): void
    {
        // Migrations are loaded by path, not autoloaded.
        foreach (glob(__DIR__ . '/../../../../migrations/Billing/*.php') ?: [] as $file) {
            require_once $file;
        }
    }

    protected function setUp(): void
    {
        $default = self::getContainer()->get('doctrine.dbal.default_connection');
        \assert($default instanceof Connection);

        $params = $default->getParams();
        unset($params['dbname']);

        $server = DriverManager::getConnection($params);
        $server->executeStatement('DROP DATABASE IF EXISTS ' . self::DATABASE);
        $server->executeStatement('CREATE DATABASE ' . self::DATABASE);
        $server->close();

        $this->scratch = DriverManager::getConnection([...$params, 'dbname' => self::DATABASE]);

        foreach ([Version20260822000000::class, Version20261014200000::class, Version20261016100000::class, Version20261017120000::class] as $migration) {
            $this->migrate(new $migration($this->scratch, new NullLogger()));
        }
    }

    protected function tearDown(): void
    {
        $this->scratch->executeStatement('DROP DATABASE ' . self::DATABASE);
        $this->scratch->close();

        parent::tearDown();
    }

    /**
     * The review case: 100 € incl. VAT at 20 %, 10 % off, 90 € paid — nothing
     * is left to pay after the migration.
     */
    public function testAnIssuedDiscountedInvoiceKeepsItsAmounts(): void
    {
        $this->invoice('a1', 'PAID', 'PERCENTAGE', '10', [['20.0000', 8333, 1667]]);
        $this->scratch->insert('billing__payments', [
            'id'                 => self::id('p1'),
            'invoice_id'         => self::id('a1'),
            'clinic_id'          => self::id('c1'),
            'method'             => 'CARD',
            'amount_minor_units' => 9000,
            'currency'           => 'EUR',
            'received_at'        => '2026-10-01 10:00:00',
            'position'           => 0,
        ]);

        $this->migrate(new Version20261021110000($this->scratch, new NullLogger()));

        $row = $this->row('a1');
        self::assertSame([8333, 833, 7500, 1500, 9000], [$row['net'], $row['allowance'], $row['basis'], $row['tax'], $row['gross']]);
        self::assertSame([['vat' => 1500, 'rate' => '20', 'allowance' => 833, 'lineTotal' => 8333]], $row['breakdown']);
        $paid = $this->scratch->fetchOne('SELECT SUM(amount_minor_units) FROM billing__payments WHERE invoice_id = ?', [self::id('a1')]);
        self::assertIsNumeric($paid);
        self::assertSame(0, $row['gross'] - (int) $paid);
    }

    public function testTheDiscountAndTheVatAreSharedBetweenTheRates(): void
    {
        // 50 € excl. VAT at 20 % and 30 € excl. VAT at 5.5 %, 10 € off.
        $this->invoice('a2', 'ISSUED', 'FIXED', '1000', [['20.0000', 5000, 1000], ['5.5000', 3000, 165], ['20.0000', 0, 0]]);
        $this->invoice('a3', 'ISSUED', null, null, [['10.0000', 1234, 123]]);
        $this->invoice('a4', 'DRAFT', null, null, []);

        $this->migrate(new Version20261021110000($this->scratch, new NullLogger()));

        $discounted = $this->row('a2');
        self::assertSame([8000, 1000, 7000, 1019, 8019], [$discounted['net'], $discounted['allowance'], $discounted['basis'], $discounted['tax'], $discounted['gross']]);
        self::assertSame([
            ['vat' => 875, 'rate' => '20', 'allowance' => 625, 'lineTotal' => 5000],
            ['vat' => 144, 'rate' => '5.5', 'allowance' => 375, 'lineTotal' => 3000],
        ], $discounted['breakdown']);

        $plain = $this->row('a3');
        self::assertSame([1234, 0, 1234, 123, 1357], [$plain['net'], $plain['allowance'], $plain['basis'], $plain['tax'], $plain['gross']]);

        $empty = $this->row('a4');
        self::assertSame([0, 0, 0, 0, 0, []], [$empty['net'], $empty['allowance'], $empty['basis'], $empty['tax'], $empty['gross'], $empty['breakdown']]);
    }

    /**
     * @param list<array{string, int, int}> $lines rate, net, tax
     */
    private function invoice(string $id, string $status, ?string $discountKind, ?string $discountValue, array $lines): void
    {
        $this->scratch->insert('billing__invoices', [
            'id'                  => self::id($id),
            'clinic_id'           => self::id('c1'),
            'number'              => 'DRAFT' === $status ? null : 'FAC-2026-' . $id,
            'status'              => $status,
            'payer_name'          => 'EARL Élevage des Calanques',
            'payer_address_lines' => '[]',
            'currency'            => 'EUR',
            'discount_kind'       => $discountKind,
            'discount_value'      => $discountValue,
            'created_at'          => '2026-10-01 09:00:00',
            'updated_at'          => '2026-10-01 09:00:00',
            'issued_at'           => 'DRAFT' === $status ? null : '2026-10-01 09:30:00',
        ]);

        foreach ($lines as $i => [$rate, $net, $tax]) {
            $this->scratch->insert('billing__invoice_lines', [
                'id'                     => self::id($id . 'l' . $i),
                'invoice_id'             => self::id($id),
                'label'                  => 'Ligne ' . $i,
                'quantity'               => '1.00',
                'unit_price_minor_units' => $net,
                'net_minor_units'        => $net,
                'tax_minor_units'        => $tax,
                'gross_minor_units'      => $net + $tax,
                'tax_rate_percent'       => $rate,
                'source'                 => 'MANUAL',
                'position'               => $i,
            ]);
        }
    }

    /**
     * @return array{net: int, allowance: int, basis: int, tax: int, gross: int, breakdown: mixed}
     */
    private function row(string $id): array
    {
        $row = $this->scratch->fetchAssociative(
            'SELECT total_net_before_allowance, total_allowance, total_tax_basis, total_tax, total_gross, tax_breakdown FROM billing__invoices WHERE id = ?',
            [self::id($id)],
        );
        \assert(\is_array($row));

        return [
            'net'       => RowAccessor::int($row, 'total_net_before_allowance'),
            'allowance' => RowAccessor::int($row, 'total_allowance'),
            'basis'     => RowAccessor::int($row, 'total_tax_basis'),
            'tax'       => RowAccessor::int($row, 'total_tax'),
            'gross'     => RowAccessor::int($row, 'total_gross'),
            'breakdown' => json_decode(RowAccessor::string($row, 'tax_breakdown'), true, 512, \JSON_THROW_ON_ERROR),
        ];
    }

    private function migrate(AbstractMigration $migration): void
    {
        $migration->up(new Schema());

        foreach ($migration->getSql() as $query) {
            /** @var array<string, mixed> $parameters */
            $parameters = $query->getParameters();
            /** @var array<string, string> $types */
            $types = $query->getTypes();

            $this->scratch->executeStatement($query->getStatement(), $parameters, $types);
        }
    }

    private static function id(string $name): string
    {
        return str_pad($name, 16, "\0");
    }
}
