<?php

declare(strict_types=1);

namespace DoctrineMigrations\Client;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260117210757 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE client__client (
              id BINARY(16) NOT NULL,
              clinic_id BINARY(16) NOT NULL,
              first_name VARCHAR(255) NOT NULL,
              last_name VARCHAR(255) NOT NULL,
              postal_address_street_line_1 VARCHAR(255) DEFAULT NULL,
              postal_address_street_line_2 VARCHAR(255) DEFAULT NULL,
              postal_address_postal_code VARCHAR(20) DEFAULT NULL,
              postal_address_city VARCHAR(255) DEFAULT NULL,
              postal_address_region VARCHAR(255) DEFAULT NULL,
              postal_address_country_code VARCHAR(2) DEFAULT NULL,
              status VARCHAR(20) NOT NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME NOT NULL,
              INDEX idx_client_clinic_id (clinic_id),
              INDEX idx_client_status (status),
              INDEX idx_client_created_at (created_at),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE client__contact_method (
              id BINARY(16) NOT NULL,
              client_id BINARY(16) NOT NULL,
              type VARCHAR(20) NOT NULL,
              label VARCHAR(20) NOT NULL,
              value VARCHAR(255) NOT NULL,
              is_primary TINYINT NOT NULL,
              INDEX idx_contact_method_client_id (client_id),
              INDEX idx_contact_method_type (type),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE client__client');
        $this->addSql('DROP TABLE client__contact_method');
    }
}
