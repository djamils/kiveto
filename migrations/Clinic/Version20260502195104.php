<?php

declare(strict_types=1);

namespace DoctrineMigrations\Clinic;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260502195104 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE
              clinic__clinics
            ADD
              country_code VARCHAR(2) NOT NULL,
            ADD
              jurisdiction_code VARCHAR(16) DEFAULT NULL,
            ADD
              currency_code VARCHAR(3) NOT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE clinic__clinics DROP country_code, DROP jurisdiction_code, DROP currency_code');
    }
}
