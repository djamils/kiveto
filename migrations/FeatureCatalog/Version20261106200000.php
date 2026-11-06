<?php

declare(strict_types=1);

namespace DoctrineMigrations\FeatureCatalog;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261106200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'FeatureCatalog: the clinics a feature is kept for, and the cut off everywhere (kill switch).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE feature_catalog__features
              ADD audience VARCHAR(8) DEFAULT 'all' NOT NULL,
              ADD cut_reason VARCHAR(255) DEFAULT NULL,
              ADD cut_at DATETIME DEFAULT NULL,
              ADD cut_by_name VARCHAR(120) DEFAULT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE feature_catalog__features DROP audience, DROP cut_reason, DROP cut_at, DROP cut_by_name');
    }
}
