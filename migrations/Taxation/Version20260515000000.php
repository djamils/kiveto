<?php

declare(strict_types=1);

namespace DoctrineMigrations\Taxation;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260515000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create taxation tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE taxation__tax_regimes (
              id           VARCHAR(16)  NOT NULL,
              name         VARCHAR(128) NOT NULL,
              country_code VARCHAR(2)   NOT NULL,
              active       TINYINT(1)   NOT NULL DEFAULT 0,
              loaded_at    DATETIME     NOT NULL,
              updated_at   DATETIME     NOT NULL,
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE taxation__tax_rates (
              id             VARCHAR(64)  NOT NULL,
              regime_id      VARCHAR(16)  NOT NULL,
              value_bp       INT          NOT NULL,
              valid_from     DATE         NOT NULL,
              valid_to       DATE         DEFAULT NULL,
              condition_json JSON         NOT NULL,
              created_at     DATETIME     NOT NULL,
              PRIMARY KEY (id),
              INDEX idx_taxation_rates_regime_validity (regime_id, valid_from, valid_to),
              CONSTRAINT fk_taxation_rate_regime FOREIGN KEY (regime_id)
                REFERENCES taxation__tax_regimes(id) ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE taxation__tax_categories (
              code         VARCHAR(64)  NOT NULL,
              display_name VARCHAR(128) NOT NULL,
              description  VARCHAR(512) DEFAULT NULL,
              active       TINYINT(1)   NOT NULL DEFAULT 1,
              created_at   DATETIME     NOT NULL,
              updated_at   DATETIME     NOT NULL,
              PRIMARY KEY (code)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE taxation__mention_templates (
              id             BINARY(16)   NOT NULL,
              regime_id      VARCHAR(16)  NOT NULL,
              code           VARCHAR(64)  NOT NULL,
              condition_json JSON         NOT NULL,
              template       LONGTEXT     NOT NULL,
              locale         VARCHAR(8)   NOT NULL,
              active         TINYINT(1)   NOT NULL DEFAULT 1,
              created_at     DATETIME     NOT NULL,
              PRIMARY KEY (id),
              INDEX idx_taxation_mentions_regime (regime_id),
              UNIQUE KEY uniq_taxation_mention_regime_code_locale (regime_id, code, locale)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE taxation__mention_templates');
        $this->addSql('DROP TABLE taxation__tax_rates');
        $this->addSql('DROP TABLE taxation__tax_categories');
        $this->addSql('DROP TABLE taxation__tax_regimes');
    }
}
