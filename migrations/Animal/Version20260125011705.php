<?php

declare(strict_types=1);

namespace DoctrineMigrations\Animal;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260125011705 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE animal__animals (
              id BINARY(16) NOT NULL,
              clinic_id BINARY(16) NOT NULL,
              name VARCHAR(255) NOT NULL,
              species VARCHAR(50) NOT NULL,
              sex VARCHAR(50) NOT NULL,
              reproductive_status VARCHAR(50) NOT NULL,
              is_mixed_breed TINYINT NOT NULL,
              breed_name VARCHAR(255) DEFAULT NULL,
              birth_date DATETIME DEFAULT NULL,
              color VARCHAR(100) DEFAULT NULL,
              photo_url VARCHAR(500) DEFAULT NULL,
              microchip_number VARCHAR(50) DEFAULT NULL,
              tattoo_number VARCHAR(50) DEFAULT NULL,
              passport_number VARCHAR(50) DEFAULT NULL,
              registry_type VARCHAR(50) NOT NULL,
              registry_number VARCHAR(50) DEFAULT NULL,
              sire_number VARCHAR(50) DEFAULT NULL,
              life_status VARCHAR(50) NOT NULL,
              deceased_at DATETIME DEFAULT NULL,
              missing_since DATETIME DEFAULT NULL,
              transfer_status VARCHAR(50) NOT NULL,
              sold_at DATETIME DEFAULT NULL,
              given_at DATETIME DEFAULT NULL,
              auxiliary_contact_first_name VARCHAR(255) DEFAULT NULL,
              auxiliary_contact_last_name VARCHAR(255) DEFAULT NULL,
              auxiliary_contact_phone_number VARCHAR(50) DEFAULT NULL,
              status VARCHAR(20) NOT NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME NOT NULL,
              INDEX idx_animal_clinic (clinic_id),
              INDEX idx_animal_status (status),
              INDEX idx_animal_microchip (microchip_number),
              UNIQUE INDEX uniq_animal_microchip_clinic (clinic_id, microchip_number),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE animal__ownerships (
              id INT AUTO_INCREMENT NOT NULL,
              client_id BINARY(16) NOT NULL,
              role VARCHAR(50) NOT NULL,
              status VARCHAR(50) NOT NULL,
              started_at DATETIME NOT NULL,
              ended_at DATETIME DEFAULT NULL,
              animal_id BINARY(16) NOT NULL,
              INDEX idx_ownership_animal (animal_id),
              INDEX idx_ownership_client (client_id),
              INDEX idx_ownership_status (status),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              animal__ownerships
            ADD
              CONSTRAINT FK_28852B7B8E962C16 FOREIGN KEY (animal_id) REFERENCES animal__animals (id) ON DELETE CASCADE
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE animal__ownerships DROP FOREIGN KEY FK_28852B7B8E962C16');
        $this->addSql('DROP TABLE animal__animals');
        $this->addSql('DROP TABLE animal__ownerships');
    }
}
