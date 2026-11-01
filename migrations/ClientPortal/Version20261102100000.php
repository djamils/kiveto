<?php

declare(strict_types=1);

namespace DoctrineMigrations\ClientPortal;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261102100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'ClientPortal: the new clients\' booking requests through the clinic directory (slice 7, S7d).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE client_portal__booking_requests (
              id BINARY(16) NOT NULL,
              user_id BINARY(16) NOT NULL,
              clinic_id BINARY(16) NOT NULL,
              listing_id BINARY(16) NOT NULL,
              appointment_id BINARY(16) NOT NULL,
              motif_label VARCHAR(80) NOT NULL,
              start_at DATETIME NOT NULL,
              new_pet_name VARCHAR(80) DEFAULT NULL,
              new_pet_species VARCHAR(8) DEFAULT NULL,
              requester_first_name VARCHAR(60) DEFAULT NULL,
              requester_last_name VARCHAR(60) DEFAULT NULL,
              requester_phone VARCHAR(20) DEFAULT NULL,
              status VARCHAR(10) NOT NULL,
              decline_reason VARCHAR(16) DEFAULT NULL,
              client_id BINARY(16) DEFAULT NULL,
              created_at DATETIME NOT NULL,
              resolved_at DATETIME DEFAULT NULL,
              UNIQUE INDEX uniq_booking_request_appointment (appointment_id),
              INDEX idx_booking_request_user (user_id, created_at, id),
              INDEX idx_booking_request_clinic_status (clinic_id, status),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE client_portal__booking_requests');
    }
}
