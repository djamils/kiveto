<?php

declare(strict_types=1);

namespace DoctrineMigrations\Money;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260514000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create money__currencies and money__exchange_rates tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE money__currencies (
              code        CHAR(3)       NOT NULL,
              symbol      VARCHAR(8)    NOT NULL,
              decimals    SMALLINT      NOT NULL,
              display_name VARCHAR(64)  NOT NULL,
              active      TINYINT(1)    NOT NULL DEFAULT 1,
              created_at  DATETIME      NOT NULL,
              updated_at  DATETIME      NOT NULL,
              PRIMARY KEY (code)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE money__exchange_rates (
              id             BINARY(16)     NOT NULL,
              currency_from  CHAR(3)        NOT NULL,
              currency_to    CHAR(3)        NOT NULL,
              rate           DECIMAL(20,10) NOT NULL,
              effective_date DATE           NOT NULL,
              source         VARCHAR(32)    NOT NULL,
              created_at     DATETIME       NOT NULL,
              UNIQUE INDEX uniq_money_xr_from_to_date_src (currency_from, currency_to, effective_date, source),
              INDEX idx_money_xr_from_to_date (currency_from, currency_to, effective_date),
              CONSTRAINT fk_money_xr_currency_from FOREIGN KEY (currency_from) REFERENCES money__currencies (code),
              CONSTRAINT fk_money_xr_currency_to   FOREIGN KEY (currency_to)   REFERENCES money__currencies (code),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE money__exchange_rates');
        $this->addSql('DROP TABLE money__currencies');
    }
}
