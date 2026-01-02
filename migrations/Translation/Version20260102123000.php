<?php

declare(strict_types=1);

namespace DoctrineMigrations\Translation;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260102123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create translation_entry with created/updated metadata and description';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE translation_entry (
                id BINARY(16) NOT NULL,
                app_scope VARCHAR(32) NOT NULL,
                locale VARCHAR(16) NOT NULL,
                domain VARCHAR(64) NOT NULL,
                translation_key VARCHAR(190) NOT NULL,
                translation_value LONGTEXT NOT NULL,
                description LONGTEXT DEFAULT NULL,
                created_at DATETIME(6) NOT NULL,
                created_by BINARY(16) DEFAULT NULL,
                updated_at DATETIME(6) NOT NULL,
                updated_by BINARY(16) DEFAULT NULL,
                UNIQUE INDEX uniq_translation_entry_scope_locale_domain_key (app_scope, locale, domain, translation_key),
                INDEX idx_translation_scope_locale_domain (app_scope, locale, domain),
                INDEX idx_translation_key (translation_key),
                INDEX idx_translation_domain (domain),
                INDEX idx_translation_locale (locale),
                INDEX idx_translation_updated_at (updated_at),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE translation_entry');
    }
}

