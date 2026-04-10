<?php

declare(strict_types=1);

namespace DoctrineMigrations\AccessControl;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260410000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create RBAC projection tables: role_assignments and role_permissions';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE access_control__role_assignments (
              id BINARY(16) NOT NULL,
              subject_id BINARY(16) NOT NULL,
              tenant_id BINARY(16) NOT NULL,
              role_key VARCHAR(60) NOT NULL,
              UNIQUE INDEX uniq_subject_tenant (subject_id, tenant_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE access_control__role_permissions (
              id BINARY(16) NOT NULL,
              role_key VARCHAR(60) NOT NULL,
              permission VARCHAR(100) NOT NULL,
              INDEX idx_role_key (role_key),
              UNIQUE INDEX uniq_role_permission (role_key, permission),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE access_control__role_assignments');
        $this->addSql('DROP TABLE access_control__role_permissions');
    }
}
