<?php

declare(strict_types=1);

namespace DoctrineMigrations\Notification;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261027101000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Notification tables: clinic messaging profiles, veterinarian counts, SMS thresholds crossed.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE notification__clinic_messaging_profiles (
              clinic_id BINARY(16) NOT NULL,
              name VARCHAR(255) NOT NULL,
              email_local_part VARCHAR(64) NOT NULL,
              reply_to VARCHAR(320) DEFAULT NULL,
              sms_sender VARCHAR(11) NOT NULL,
              overage_cap_segments INT NOT NULL,
              address_line VARCHAR(255) NOT NULL,
              city VARCHAR(120) NOT NULL,
              phone_display VARCHAR(40) NOT NULL,
              brand_color VARCHAR(7) DEFAULT NULL,
              logo_storage_key VARCHAR(255) DEFAULT NULL,
              updated_at DATETIME NOT NULL,
              PRIMARY KEY (clinic_id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE notification__veterinarian_counts (
              clinic_id BINARY(16) NOT NULL,
              day DATE NOT NULL,
              count INT NOT NULL,
              PRIMARY KEY (clinic_id, day)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE notification__sms_thresholds_crossed (
              clinic_id BINARY(16) NOT NULL,
              month VARCHAR(7) NOT NULL,
              threshold VARCHAR(8) NOT NULL,
              crossed_at DATETIME NOT NULL,
              PRIMARY KEY (clinic_id, month, threshold)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE notification__sms_thresholds_crossed');
        $this->addSql('DROP TABLE notification__veterinarian_counts');
        $this->addSql('DROP TABLE notification__clinic_messaging_profiles');
    }
}
