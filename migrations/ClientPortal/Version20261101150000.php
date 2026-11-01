<?php

declare(strict_types=1);

namespace DoctrineMigrations\ClientPortal;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261101150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'ClientPortal: owners\' requests — pre-visit questionnaires, pet change and prescription renewal requests (slice 6).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE client_portal__pre_visit_questionnaires (
              id BINARY(16) NOT NULL,
              clinic_id BINARY(16) NOT NULL,
              client_id BINARY(16) NOT NULL,
              user_id BINARY(16) NOT NULL,
              appointment_id BINARY(16) NOT NULL,
              version SMALLINT NOT NULL,
              answers JSON NOT NULL,
              status VARCHAR(16) NOT NULL,
              submitted_at DATETIME NOT NULL,
              updated_at DATETIME NOT NULL,
              locked_at DATETIME DEFAULT NULL,
              closed_at DATETIME DEFAULT NULL,
              closed_reason VARCHAR(32) DEFAULT NULL,
              UNIQUE INDEX uniq_questionnaire_appointment (clinic_id, appointment_id),
              INDEX idx_questionnaire_client (clinic_id, client_id, status),
              INDEX idx_questionnaire_user (user_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE client_portal__pet_change_requests (
              id BINARY(16) NOT NULL,
              clinic_id BINARY(16) NOT NULL,
              client_id BINARY(16) NOT NULL,
              user_id BINARY(16) NOT NULL,
              pet_id BINARY(16) NOT NULL,
              field VARCHAR(16) NOT NULL,
              proposed_value VARCHAR(120) NOT NULL,
              normalised_value JSON NOT NULL,
              current_value_at_submit VARCHAR(120) DEFAULT NULL,
              note VARCHAR(500) DEFAULT NULL,
              status VARCHAR(16) NOT NULL,
              submitted_at DATETIME NOT NULL,
              updated_at DATETIME NOT NULL,
              resolved_at DATETIME DEFAULT NULL,
              resolved_by_user_id BINARY(16) DEFAULT NULL,
              resolved_by_name VARCHAR(255) DEFAULT NULL,
              resolution_message VARCHAR(280) DEFAULT NULL,
              notification_message_id BINARY(16) DEFAULT NULL,
              client_unreachable TINYINT(1) NOT NULL,
              closed_at DATETIME DEFAULT NULL,
              closed_reason VARCHAR(32) DEFAULT NULL,
              INDEX idx_change_request_queue (clinic_id, status, submitted_at),
              INDEX idx_change_request_pet_field (clinic_id, pet_id, field, status),
              INDEX idx_change_request_client (clinic_id, client_id, status),
              INDEX idx_change_request_user (user_id, clinic_id, submitted_at),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);

        // `open_key` holds the prescription while the request is submitted:
        // the unique key allows a single open request per prescription.
        $this->addSql(<<<'SQL'
            CREATE TABLE client_portal__prescription_renewal_requests (
              id BINARY(16) NOT NULL,
              clinic_id BINARY(16) NOT NULL,
              client_id BINARY(16) NOT NULL,
              user_id BINARY(16) NOT NULL,
              prescription_id BINARY(16) NOT NULL,
              pet_id BINARY(16) NOT NULL,
              pet_condition VARCHAR(16) NOT NULL,
              note VARCHAR(500) DEFAULT NULL,
              resulting_prescription_id BINARY(16) DEFAULT NULL,
              status VARCHAR(16) NOT NULL,
              submitted_at DATETIME NOT NULL,
              updated_at DATETIME NOT NULL,
              resolved_at DATETIME DEFAULT NULL,
              resolved_by_user_id BINARY(16) DEFAULT NULL,
              resolved_by_name VARCHAR(255) DEFAULT NULL,
              resolution_message VARCHAR(280) DEFAULT NULL,
              notification_message_id BINARY(16) DEFAULT NULL,
              client_unreachable TINYINT(1) NOT NULL,
              closed_at DATETIME DEFAULT NULL,
              closed_reason VARCHAR(32) DEFAULT NULL,
              open_key BINARY(16) GENERATED ALWAYS AS (IF(status = 'submitted', prescription_id, NULL)) STORED,
              UNIQUE INDEX uniq_renewal_request_open (clinic_id, open_key),
              INDEX idx_renewal_request_prescription (clinic_id, prescription_id, submitted_at),
              INDEX idx_renewal_request_queue (clinic_id, status, submitted_at),
              INDEX idx_renewal_request_pet (clinic_id, pet_id, status),
              INDEX idx_renewal_request_client (clinic_id, client_id, status),
              INDEX idx_renewal_request_user (user_id, clinic_id, submitted_at),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE client_portal__prescription_renewal_requests');
        $this->addSql('DROP TABLE client_portal__pet_change_requests');
        $this->addSql('DROP TABLE client_portal__pre_visit_questionnaires');
    }
}
