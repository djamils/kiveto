<?php

declare(strict_types=1);

namespace DoctrineMigrations\Shared;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261029120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Shared: idempotency keys (Portal API) and rate limiter cache (DoctrineDbalAdapter default columns)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE shared__idempotency_keys (
              principal_id BINARY(16) NOT NULL,
              idempotency_key VARCHAR(128) NOT NULL,
              request_fingerprint CHAR(64) NOT NULL,
              response_status SMALLINT UNSIGNED NOT NULL,
              response_body JSON DEFAULT NULL,
              created_at DATETIME NOT NULL,
              expires_at DATETIME NOT NULL,
              INDEX idx_idempotency_key_expires_at (expires_at),
              PRIMARY KEY (principal_id, idempotency_key)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_bin`
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE shared__rate_limiter_cache (
              item_id VARBINARY(255) NOT NULL,
              item_data MEDIUMBLOB NOT NULL,
              item_lifetime INT UNSIGNED DEFAULT NULL,
              item_time INT UNSIGNED NOT NULL,
              PRIMARY KEY (item_id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE shared__rate_limiter_cache');
        $this->addSql('DROP TABLE shared__idempotency_keys');
    }
}
