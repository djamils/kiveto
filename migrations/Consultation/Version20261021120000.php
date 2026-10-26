<?php

declare(strict_types=1);

namespace DoctrineMigrations\Consultation;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261021120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Consultation price snapshots keep the mode the catalogue price was entered in (incl / excl tax); existing snapshots were excluding tax.';
    }

    public function up(Schema $schema): void
    {
        // Existing snapshots copied catalogue prices stored excluding tax: they become 'excl'.
        $this->addSql("ALTER TABLE consultation__billing_lines ADD unit_price_mode VARCHAR(4) NOT NULL DEFAULT 'excl' AFTER currency");
        $this->addSql('ALTER TABLE consultation__billing_lines ALTER unit_price_mode DROP DEFAULT');

        $this->addSql("ALTER TABLE consultation__prescription_lines ADD unit_price_mode VARCHAR(4) NOT NULL DEFAULT 'excl' AFTER currency");
        $this->addSql('ALTER TABLE consultation__prescription_lines ALTER unit_price_mode DROP DEFAULT');

        // A plan action without a price (not billable) has no mode either.
        $this->addSql('ALTER TABLE consultation__plan_actions ADD unit_price_mode VARCHAR(4) DEFAULT NULL AFTER currency');
        $this->addSql("UPDATE consultation__plan_actions SET unit_price_mode = 'excl' WHERE unit_price_minor_units IS NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE consultation__plan_actions DROP unit_price_mode');
        $this->addSql('ALTER TABLE consultation__prescription_lines DROP unit_price_mode');
        $this->addSql('ALTER TABLE consultation__billing_lines DROP unit_price_mode');
    }
}
