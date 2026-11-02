<?php

declare(strict_types=1);

namespace DoctrineMigrations\ClientPortal;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261102180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'ClientPortal: who answered a « Nouveau client » request, and the record of each « Rattachement » answer (S6-F3).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE client_portal__booking_requests ADD resolved_by_user_id BINARY(16) DEFAULT NULL, ADD resolved_by_name VARCHAR(255) DEFAULT NULL');
        $this->addSql(<<<'SQL'
            CREATE TABLE client_portal__link_decisions (
              id BINARY(16) NOT NULL,
              clinic_id BINARY(16) NOT NULL,
              user_id BINARY(16) NOT NULL,
              outcome VARCHAR(10) NOT NULL,
              client_ids JSON NOT NULL,
              decided_at DATETIME NOT NULL,
              decided_by_user_id BINARY(16) NOT NULL,
              decided_by_name VARCHAR(255) NOT NULL,
              INDEX idx_link_decision_clinic (clinic_id, decided_at, id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE client_portal__link_decisions');
        $this->addSql('ALTER TABLE client_portal__booking_requests DROP resolved_by_user_id, DROP resolved_by_name');
    }
}
