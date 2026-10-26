<?php

declare(strict_types=1);

namespace DoctrineMigrations\Billing;

use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261021110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Invoice lines keep the entered price, its mode and the line nature; documents freeze their per-rate breakdown and totals.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE billing__invoice_lines
              ADD unit_price_mode VARCHAR(4) NOT NULL DEFAULT 'excl' AFTER unit_price_minor_units,
              ADD unit_net_micro_units BIGINT NOT NULL DEFAULT 0 AFTER unit_price_mode,
              ADD operation_category VARCHAR(16) DEFAULT NULL AFTER tax_category_code
        SQL);
        $this->addSql(<<<'SQL'
            UPDATE billing__invoice_lines
               SET unit_net_micro_units = unit_price_minor_units * 10000,
                   operation_category = CASE
                       WHEN tax_category_code LIKE 'veterinary.act.%' OR tax_category_code LIKE 'veterinary.service.%' THEN 'services'
                       WHEN tax_category_code LIKE 'veterinary.drug.%' OR tax_category_code LIKE 'veterinary.food.%'
                         OR tax_category_code LIKE 'veterinary.consumable.%' THEN 'goods'
                       ELSE NULL
                   END
        SQL);

        foreach (['billing__invoices', 'billing__credit_notes'] as $table) {
            $this->addSql(<<<SQL
                ALTER TABLE {$table}
                  ADD tax_breakdown JSON DEFAULT NULL,
                  ADD total_net_before_allowance INT NOT NULL DEFAULT 0,
                  ADD total_allowance INT NOT NULL DEFAULT 0
            SQL);
        }

        $this->addSql(<<<'SQL'
            ALTER TABLE billing__invoices
              ADD total_tax_basis INT NOT NULL DEFAULT 0,
              ADD total_tax INT NOT NULL DEFAULT 0,
              ADD total_gross INT NOT NULL DEFAULT 0
        SQL);

        // Existing invoices are frozen with the amounts they were issued with —
        // the stored lines and the discount, as the previous code computed
        // them — never recalculated. Drafts are recalculated when loaded.
        foreach ($this->connection->fetchAllAssociative('SELECT id, discount_kind, discount_value FROM billing__invoices') as $invoice) {
            $lines = $this->connection->fetchAllAssociative(
                'SELECT tax_rate_percent AS rate, net_minor_units AS net, tax_minor_units AS tax FROM billing__invoice_lines WHERE invoice_id = :id',
                ['id' => $invoice['id']],
            );
            $totals = self::issuedTotals(
                array_map(static fn (array $line): array => [
                    'rate' => self::rate(RowAccessor::string($line, 'rate')),
                    'net'  => RowAccessor::int($line, 'net'),
                    'tax'  => RowAccessor::int($line, 'tax'),
                ], $lines),
                RowAccessor::nullableString($invoice, 'discount_kind'),
                RowAccessor::nullableString($invoice, 'discount_value'),
            );

            $this->addSql(
                'UPDATE billing__invoices
                    SET tax_breakdown = :breakdown, total_net_before_allowance = :net, total_allowance = :allowance,
                        total_tax_basis = :basis, total_tax = :tax, total_gross = :gross
                  WHERE id = :id',
                [
                    'breakdown' => json_encode($totals['breakdown'], \JSON_THROW_ON_ERROR),
                    'net'       => $totals['net'],
                    'allowance' => $totals['allowance'],
                    'basis'     => $totals['net'] - $totals['allowance'],
                    'tax'       => $totals['tax'],
                    'gross'     => $totals['net'] - $totals['allowance'] + $totals['tax'],
                    'id'        => $invoice['id'],
                ],
            );
        }

        $this->addSql('ALTER TABLE billing__invoice_lines DROP tax_minor_units');

        $notes = $this->connection->fetchAllAssociative('SELECT id, line_items FROM billing__credit_notes');

        foreach ($notes as $note) {
            $raw = $note['line_items'];
            \assert(\is_string($raw));

            /** @var list<array<string, int|string|null>> $lines */
            $lines   = json_decode($raw, true, 512, \JSON_THROW_ON_ERROR);
            $buckets = [];

            foreach ($lines as $i => $line) {
                $rate = self::rate((string) $line['taxRatePercent']);

                $buckets[$rate] ??= ['rate' => $rate, 'lineTotal' => 0, 'allowance' => 0, 'vat' => 0];
                $buckets[$rate]['lineTotal'] += (int) $line['net'];
                $buckets[$rate]['vat'] += (int) ($line['tax'] ?? 0);

                $lines[$i]['unitPrice']     = (int) ($line['unitNet'] ?? 0);
                $lines[$i]['unitPriceMode'] = 'excl';
                $lines[$i]['unitNetMicro']  = (int) ($line['unitNet'] ?? 0) * 10000;
                unset($lines[$i]['unitNet'], $lines[$i]['tax']);
            }

            $this->addSql(
                'UPDATE billing__credit_notes SET line_items = :lines, tax_breakdown = :breakdown, total_net_before_allowance = total_net_minor_units WHERE id = :id',
                [
                    'lines'     => json_encode($lines, \JSON_THROW_ON_ERROR),
                    'breakdown' => json_encode(array_values($buckets), \JSON_THROW_ON_ERROR),
                    'id'        => $note['id'],
                ],
            );
        }

        $this->addSql('UPDATE billing__credit_notes SET tax_breakdown = JSON_ARRAY() WHERE tax_breakdown IS NULL');

        foreach (['billing__invoices', 'billing__credit_notes'] as $table) {
            $this->addSql("ALTER TABLE {$table} MODIFY tax_breakdown JSON NOT NULL");
        }

        $this->addSql('ALTER TABLE billing__invoice_lines ALTER unit_price_mode DROP DEFAULT, ALTER unit_net_micro_units DROP DEFAULT');
    }

    /**
     * The totals an invoice was issued with: its lines, less the discount
     * taken off their net, the VAT scaled by the same ratio (the previous
     * rules of Invoice and Discount). Per rate, the discount and the VAT are
     * shared pro rata so that the rates add up to the totals exactly.
     *
     * @param list<array{rate: string, net: int, tax: int}> $lines
     *
     * @return array{net: int, allowance: int, tax: int, breakdown: list<array{rate: string, lineTotal: int, allowance: int, vat: int}>}
     */
    public static function issuedTotals(array $lines, ?string $discountKind, ?string $discountValue): array
    {
        $buckets = [];

        foreach ($lines as $line) {
            $buckets[$line['rate']] ??= ['rate' => $line['rate'], 'lineTotal' => 0, 'tax' => 0];
            $buckets[$line['rate']]['lineTotal'] += $line['net'];
            $buckets[$line['rate']]['tax'] += $line['tax'];
        }

        $net    = array_sum(array_column($buckets, 'lineTotal'));
        $rawTax = array_sum(array_column($buckets, 'tax'));
        $off    = 0;

        if (null !== $discountKind && null !== $discountValue && $net > 0) {
            $off = min($net, 'PERCENTAGE' === $discountKind ? (int) round($net * (float) $discountValue / 100) : (int) $discountValue);
        }

        $tax        = 0 === $off ? $rawTax : (int) round($rawTax * ($net - $off) / $net);
        $allowances = self::share($off, array_column($buckets, 'lineTotal'));
        $taxes      = self::share($tax, array_column($buckets, 'tax'));
        $breakdown  = [];

        foreach (array_values($buckets) as $i => $bucket) {
            $breakdown[] = ['rate' => $bucket['rate'], 'lineTotal' => $bucket['lineTotal'], 'allowance' => $allowances[$i], 'vat' => $taxes[$i]];
        }

        return ['net' => $net, 'allowance' => $off, 'tax' => $tax, 'breakdown' => $breakdown];
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE billing__invoice_lines ADD tax_minor_units INT NOT NULL DEFAULT 0');
        $this->addSql('UPDATE billing__invoice_lines SET tax_minor_units = gross_minor_units - net_minor_units');
        $this->addSql('ALTER TABLE billing__invoice_lines DROP unit_price_mode, DROP unit_net_micro_units, DROP operation_category');
        $this->addSql('ALTER TABLE billing__invoices DROP tax_breakdown, DROP total_net_before_allowance, DROP total_allowance, DROP total_tax_basis, DROP total_tax, DROP total_gross');
        $this->addSql('ALTER TABLE billing__credit_notes DROP tax_breakdown, DROP total_net_before_allowance, DROP total_allowance');
    }

    /**
     * Splits $total pro rata of $weights, the remainder going to the largest
     * fractions, so that the parts add up to $total exactly.
     *
     * @param list<int> $weights
     *
     * @return list<int>
     */
    private static function share(int $total, array $weights): array
    {
        $sum = array_sum($weights);

        if (0 === $sum) {
            return array_map(static fn (int $i): int => 0 === $i ? $total : 0, array_keys($weights));
        }

        $parts     = [];
        $fractions = [];

        foreach ($weights as $i => $weight) {
            $exact         = $total * $weight / $sum;
            $parts[$i]     = (int) floor($exact);
            $fractions[$i] = $exact - $parts[$i];
        }

        arsort($fractions);

        foreach (\array_slice(array_keys($fractions), 0, $total - array_sum($parts)) as $i) {
            ++$parts[$i];
        }

        return array_values($parts);
    }

    /** « 20.0000 » → « 20 », « 5.5000 » → « 5.5 ». */
    private static function rate(string $rate): string
    {
        return str_contains($rate, '.') ? mb_rtrim(mb_rtrim($rate, '0'), '.') : $rate;
    }
}
