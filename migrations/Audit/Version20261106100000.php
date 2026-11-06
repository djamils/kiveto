<?php

declare(strict_types=1);

namespace DoctrineMigrations\Audit;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261106100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Audit: the append-only audit log of the console.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE audit__entries (
                id BINARY(16) NOT NULL,
                occurred_at DATETIME NOT NULL,
                actor_kind VARCHAR(10) NOT NULL,
                actor_id BINARY(16) DEFAULT NULL,
                actor_name VARCHAR(160) DEFAULT NULL,
                action VARCHAR(64) NOT NULL,
                category VARCHAR(16) NOT NULL,
                summary VARCHAR(255) NOT NULL,
                clinic_id BINARY(16) DEFAULT NULL,
                clinic_name VARCHAR(160) DEFAULT NULL,
                target_user_id BINARY(16) DEFAULT NULL,
                ip_address VARCHAR(45) DEFAULT NULL,
                details JSON NOT NULL,
                INDEX idx_audit_entry_occurred (occurred_at),
                INDEX idx_audit_entry_clinic (clinic_id, occurred_at),
                INDEX idx_audit_entry_target (target_user_id, occurred_at),
                INDEX idx_audit_entry_actor (actor_id, occurred_at),
                INDEX idx_audit_entry_category (category, occurred_at),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE audit__entries');
    }
}
