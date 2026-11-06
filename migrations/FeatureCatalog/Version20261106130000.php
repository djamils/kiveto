<?php

declare(strict_types=1);

namespace DoctrineMigrations\FeatureCatalog;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261106130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'FeatureCatalog: the global settings of the features and their state per clinic.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE feature_catalog__features (
              code VARCHAR(64) NOT NULL,
              status VARCHAR(16) NOT NULL,
              default_on TINYINT(1) NOT NULL,
              updated_at DATETIME DEFAULT NULL,
              PRIMARY KEY (code)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
            SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE feature_catalog__clinic_features (
              clinic_id BINARY(16) NOT NULL,
              code VARCHAR(64) NOT NULL,
              enabled TINYINT(1) NOT NULL,
              changed_at DATETIME NOT NULL,
              changed_by_id BINARY(16) DEFAULT NULL,
              changed_by_name VARCHAR(160) DEFAULT NULL,
              INDEX idx_clinic_feature_code (code),
              PRIMARY KEY (clinic_id, code)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE feature_catalog__clinic_features');
        $this->addSql('DROP TABLE feature_catalog__features');
    }
}
