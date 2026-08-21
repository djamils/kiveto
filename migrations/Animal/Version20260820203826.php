<?php

declare(strict_types=1);

namespace DoctrineMigrations\Animal;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260820203826 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create animal__medical_alerts (allergies and chronic conditions)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE animal__medical_alerts (
              id BINARY(16) NOT NULL,
              kind VARCHAR(30) NOT NULL,
              label VARCHAR(120) NOT NULL,
              note LONGTEXT DEFAULT NULL,
              animal_id BINARY(16) NOT NULL,
              INDEX idx_medical_alert_animal (animal_id),
              INDEX idx_medical_alert_kind (kind),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              animal__medical_alerts
            ADD
              CONSTRAINT FK_F970D04B8E962C16 FOREIGN KEY (animal_id) REFERENCES animal__animals (id) ON DELETE CASCADE
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE animal__medical_alerts DROP FOREIGN KEY FK_F970D04B8E962C16');
        $this->addSql('DROP TABLE animal__medical_alerts');
    }
}
