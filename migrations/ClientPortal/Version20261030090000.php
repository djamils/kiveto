<?php

declare(strict_types=1);

namespace DoctrineMigrations\ClientPortal;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261030090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'ClientPortal: Portal invitations, links between owner accounts and clinic clients, owner profiles.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE client_portal__portal_invitations (
              id BINARY(16) NOT NULL,
              clinic_id BINARY(16) NOT NULL,
              client_id BINARY(16) NOT NULL,
              phone VARCHAR(16) NOT NULL,
              email VARCHAR(255) DEFAULT NULL,
              channel VARCHAR(10) NOT NULL,
              token_hash CHAR(64) NOT NULL,
              status VARCHAR(10) NOT NULL,
              sent_at DATETIME NOT NULL,
              expires_at DATETIME NOT NULL,
              accepted_at DATETIME DEFAULT NULL,
              accepted_by_user_id BINARY(16) DEFAULT NULL,
              revoked_at DATETIME DEFAULT NULL,
              UNIQUE INDEX uniq_portal_invitation_token (token_hash),
              INDEX idx_portal_invitation_phone (phone, status),
              INDEX idx_portal_invitation_client (clinic_id, client_id, status),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE client_portal__client_links (
              id BINARY(16) NOT NULL,
              user_id BINARY(16) NOT NULL,
              clinic_id BINARY(16) NOT NULL,
              client_id BINARY(16) NOT NULL,
              status VARCHAR(10) NOT NULL,
              linked_at DATETIME DEFAULT NULL,
              revoked_at DATETIME DEFAULT NULL,
              UNIQUE INDEX uniq_client_link (user_id, clinic_id, client_id),
              INDEX idx_client_link_client (clinic_id, client_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE client_portal__owner_profiles (
              user_id BINARY(16) NOT NULL,
              first_name VARCHAR(255) NOT NULL,
              last_name VARCHAR(255) NOT NULL,
              address_line1 VARCHAR(255) DEFAULT NULL,
              address_line2 VARCHAR(255) DEFAULT NULL,
              address_postal_code VARCHAR(20) DEFAULT NULL,
              address_city VARCHAR(255) DEFAULT NULL,
              address_country CHAR(2) DEFAULT NULL,
              terms_version VARCHAR(32) NOT NULL,
              terms_accepted_at DATETIME NOT NULL,
              created_at DATETIME NOT NULL,
              PRIMARY KEY (user_id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE client_portal__owner_profiles');
        $this->addSql('DROP TABLE client_portal__client_links');
        $this->addSql('DROP TABLE client_portal__portal_invitations');
    }
}
