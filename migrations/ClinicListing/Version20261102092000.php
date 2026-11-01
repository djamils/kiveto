<?php

declare(strict_types=1);

namespace DoctrineMigrations\ClinicListing;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261102092000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'ClinicListing: the directory projection of the clinics and their directory settings (slice 7, S7c).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE clinic_listing__clinic_listings (
              clinic_id BINARY(16) NOT NULL,
              id BINARY(16) NOT NULL,
              name VARCHAR(255) NOT NULL,
              address_line VARCHAR(255) DEFAULT NULL,
              postal_code VARCHAR(20) DEFAULT NULL,
              city VARCHAR(255) DEFAULT NULL,
              phone VARCHAR(32) DEFAULT NULL,
              lat NUMERIC(8, 6) DEFAULT NULL,
              lng NUMERIC(9, 6) DEFAULT NULL,
              address_hash CHAR(64) DEFAULT NULL,
              timezone VARCHAR(64) NOT NULL,
              opening_hours JSON NOT NULL,
              active TINYINT(1) NOT NULL,
              refreshed_at DATETIME NOT NULL,
              UNIQUE INDEX uniq_clinic_listing_id (id),
              INDEX idx_clinic_listing_active_city (active, city),
              INDEX idx_clinic_listing_lat (lat),
              INDEX idx_clinic_listing_lng (lng),
              PRIMARY KEY (clinic_id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE clinic_listing__listing_settings (
              clinic_id BINARY(16) NOT NULL,
              listed TINYINT(1) NOT NULL,
              accepts_new_clients TINYINT(1) NOT NULL,
              tags JSON NOT NULL,
              PRIMARY KEY (clinic_id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE clinic_listing__listing_settings');
        $this->addSql('DROP TABLE clinic_listing__clinic_listings');
    }
}
