<?php

declare(strict_types=1);

namespace DoctrineMigrations\Money;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260514000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create money__currencies table';
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
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE money__currencies');
    }
}
