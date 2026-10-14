<?php

declare(strict_types=1);

namespace DoctrineMigrations\Animal;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261014150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Animal sheet: circumstances of a death and disposal of the body, health insurance.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE animal__animals
              ADD death_cause VARCHAR(30) DEFAULT NULL,
              ADD body_disposal VARCHAR(30) DEFAULT NULL,
              ADD insurer_name VARCHAR(80) DEFAULT NULL,
              ADD insurance_policy_number VARCHAR(30) DEFAULT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE animal__animals
              DROP death_cause,
              DROP body_disposal,
              DROP insurer_name,
              DROP insurance_policy_number
        SQL);
    }
}
