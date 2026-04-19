<?php

declare(strict_types=1);

namespace DoctrineMigrations\Clinic;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260419000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create clinic__staff_profiles table for StaffProfile aggregate';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE clinic__staff_profiles (
                id                   BINARY(16) NOT NULL,
                membership_id        BINARY(16) NOT NULL,
                first_name           VARCHAR(80) NOT NULL,
                last_name            VARCHAR(80) NOT NULL,
                display_name         VARCHAR(60) NOT NULL,
                phone_number         VARCHAR(32) NULL,
                agenda_color         CHAR(7) NOT NULL,
                sort_order           INT NOT NULL DEFAULT 0,
                is_visible_in_agenda TINYINT(1) NOT NULL DEFAULT 1,
                registration_number  VARCHAR(32) NULL,
                professional_title   VARCHAR(8) NULL,
                signature_image_key  VARCHAR(255) NULL,
                created_at_utc       DATETIME NOT NULL,
                updated_at_utc       DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_staff_profile_membership (membership_id),
                KEY idx_staff_profile_membership_id (membership_id),
                CONSTRAINT fk_staff_profile_membership
                    FOREIGN KEY (membership_id)
                    REFERENCES clinic__clinic_memberships (id)
                    ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE clinic__staff_profiles');
    }
}
