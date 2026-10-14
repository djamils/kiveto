<?php

declare(strict_types=1);

namespace DoctrineMigrations\Animal;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261010121751 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE animal__preventive_care_records (
              id BINARY(16) NOT NULL,
              clinic_id BINARY(16) NOT NULL,
              animal_id BINARY(16) NOT NULL,
              kind VARCHAR(30) NOT NULL,
              label VARCHAR(120) NOT NULL,
              administered_on DATE NOT NULL,
              batch_number VARCHAR(60) DEFAULT NULL,
              next_due_on DATE DEFAULT NULL,
              author_name VARCHAR(255) NOT NULL,
              recorded_at DATETIME NOT NULL,
              INDEX idx_animal_preventive_care_animal (
                clinic_id, animal_id, administered_on
              ),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE animal__weight_records (
              id BINARY(16) NOT NULL,
              clinic_id BINARY(16) NOT NULL,
              animal_id BINARY(16) NOT NULL,
              weight_grams INT NOT NULL,
              body_condition_score SMALLINT DEFAULT NULL,
              measured_on DATE NOT NULL,
              author_name VARCHAR(255) NOT NULL,
              recorded_at DATETIME NOT NULL,
              INDEX idx_animal_weight_record_animal (
                clinic_id, animal_id, measured_on
              ),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE animal__preventive_care_records');
        $this->addSql('DROP TABLE animal__weight_records');
    }
}
