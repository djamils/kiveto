<?php

declare(strict_types=1);

namespace DoctrineMigrations\Billing;

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

        // Existing documents are frozen with today's sums: one bucket per rate,
        // the discount left out (the alpha database is reset with the demo).
        $this->addSql(<<<'SQL'
            UPDATE billing__invoices i
               SET total_net_before_allowance = (SELECT COALESCE(SUM(l.net_minor_units), 0) FROM billing__invoice_lines l WHERE l.invoice_id = i.id),
                   total_tax = (SELECT COALESCE(SUM(l.tax_minor_units), 0) FROM billing__invoice_lines l WHERE l.invoice_id = i.id),
                   tax_breakdown = COALESCE((
                       SELECT JSON_ARRAYAGG(JSON_OBJECT('rate', b.rate, 'lineTotal', b.net, 'allowance', 0, 'vat', b.tax))
                         FROM (SELECT TRIM(TRAILING '.' FROM TRIM(TRAILING '0' FROM l.tax_rate_percent)) AS rate,
                                      CAST(SUM(l.net_minor_units) AS SIGNED) AS net, CAST(SUM(l.tax_minor_units) AS SIGNED) AS tax
                                 FROM billing__invoice_lines l WHERE l.invoice_id = i.id GROUP BY l.tax_rate_percent) b
                   ), JSON_ARRAY())
        SQL);
        $this->addSql('UPDATE billing__invoices SET total_tax_basis = total_net_before_allowance, total_gross = total_net_before_allowance + total_tax');
        $this->addSql('ALTER TABLE billing__invoice_lines DROP tax_minor_units');

        $notes = $this->connection->fetchAllAssociative('SELECT id, line_items FROM billing__credit_notes');

        foreach ($notes as $note) {
            $raw = $note['line_items'];
            \assert(\is_string($raw));

            /** @var list<array<string, int|string|null>> $lines */
            $lines   = json_decode($raw, true, 512, \JSON_THROW_ON_ERROR);
            $buckets = [];

            foreach ($lines as $i => $line) {
                $rate = (string) $line['taxRatePercent'];
                $rate = str_contains($rate, '.') ? mb_rtrim(mb_rtrim($rate, '0'), '.') : $rate;

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

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE billing__invoice_lines ADD tax_minor_units INT NOT NULL DEFAULT 0');
        $this->addSql('UPDATE billing__invoice_lines SET tax_minor_units = gross_minor_units - net_minor_units');
        $this->addSql('ALTER TABLE billing__invoice_lines DROP unit_price_mode, DROP unit_net_micro_units, DROP operation_category');
        $this->addSql('ALTER TABLE billing__invoices DROP tax_breakdown, DROP total_net_before_allowance, DROP total_allowance, DROP total_tax_basis, DROP total_tax, DROP total_gross');
        $this->addSql('ALTER TABLE billing__credit_notes DROP tax_breakdown, DROP total_net_before_allowance, DROP total_allowance');
    }
}
