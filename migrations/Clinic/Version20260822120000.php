<?php

declare(strict_types=1);

namespace DoctrineMigrations\Clinic;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260822120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the letterhead a clinic prints on the documents it hands to its clients.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE
              clinic__clinics
            ADD
              letterhead_legal_name VARCHAR(255) DEFAULT NULL,
            ADD
              letterhead_legal_form VARCHAR(120) DEFAULT NULL,
            ADD
              letterhead_share_capital VARCHAR(60) DEFAULT NULL,
            ADD
              letterhead_siret VARCHAR(14) DEFAULT NULL,
            ADD
              letterhead_vat_number VARCHAR(20) DEFAULT NULL,
            ADD
              letterhead_rcs_city VARCHAR(120) DEFAULT NULL,
            ADD
              letterhead_rcs_number VARCHAR(40) DEFAULT NULL,
            ADD
              letterhead_ape_code VARCHAR(10) DEFAULT NULL,
            ADD
              letterhead_ordinal_number VARCHAR(40) DEFAULT NULL,
            ADD
              letterhead_street_line_1 VARCHAR(255) DEFAULT NULL,
            ADD
              letterhead_street_line_2 VARCHAR(255) DEFAULT NULL,
            ADD
              letterhead_postal_code VARCHAR(20) DEFAULT NULL,
            ADD
              letterhead_city VARCHAR(255) DEFAULT NULL,
            ADD
              letterhead_country_code VARCHAR(2) DEFAULT NULL,
            ADD
              letterhead_phone VARCHAR(20) DEFAULT NULL,
            ADD
              letterhead_email VARCHAR(255) DEFAULT NULL,
            ADD
              letterhead_website VARCHAR(255) DEFAULT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE
              clinic__clinics DROP letterhead_legal_name,
              DROP letterhead_legal_form,
              DROP letterhead_share_capital,
              DROP letterhead_siret,
              DROP letterhead_vat_number,
              DROP letterhead_rcs_city,
              DROP letterhead_rcs_number,
              DROP letterhead_ape_code,
              DROP letterhead_ordinal_number,
              DROP letterhead_street_line_1,
              DROP letterhead_street_line_2,
              DROP letterhead_postal_code,
              DROP letterhead_city,
              DROP letterhead_country_code,
              DROP letterhead_phone,
              DROP letterhead_email,
              DROP letterhead_website
        SQL);
    }
}
