<?php

declare(strict_types=1);

namespace DoctrineMigrations\Catalog;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260523000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create all Catalog BC tables: acts, articles, packages, package_components, price_lists, price_list_items, price_rules';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE catalog__acts (
              id BINARY(16) NOT NULL,
              tenant_id BINARY(16) NOT NULL,
              name VARCHAR(150) NOT NULL,
              code VARCHAR(20) NOT NULL,
              description LONGTEXT DEFAULT NULL,
              category VARCHAR(30) NOT NULL,
              tax_category_code VARCHAR(50) NOT NULL,
              base_price_minor_units INT NOT NULL,
              base_price_currency VARCHAR(3) NOT NULL,
              estimated_duration_minutes INT NOT NULL,
              requires_anesthesia TINYINT(1) NOT NULL,
              status VARCHAR(20) NOT NULL,
              created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
              updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
              INDEX idx_act_tenant (tenant_id),
              INDEX idx_act_status (status),
              UNIQUE INDEX uniq_act_code_tenant (code, tenant_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE catalog__acts
              ADD FULLTEXT INDEX ft_act_name (name)
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE catalog__articles (
              id BINARY(16) NOT NULL,
              tenant_id BINARY(16) NOT NULL,
              name VARCHAR(150) NOT NULL,
              code VARCHAR(20) NOT NULL,
              kind VARCHAR(20) NOT NULL,
              gtin VARCHAR(14) DEFAULT NULL,
              tax_category_code VARCHAR(50) NOT NULL,
              base_price_minor_units INT NOT NULL,
              base_price_currency VARCHAR(3) NOT NULL,
              unit_of_measure VARCHAR(20) NOT NULL,
              drug_auth_ref BINARY(16) DEFAULT NULL,
              drug_requires_rx TINYINT(1) DEFAULT NULL,
              drug_prescription_class VARCHAR(20) DEFAULT NULL,
              drug_is_controlled TINYINT(1) DEFAULT NULL,
              track_stock TINYINT(1) NOT NULL,
              status VARCHAR(20) NOT NULL,
              created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
              updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
              INDEX idx_article_tenant (tenant_id),
              INDEX idx_article_status (status),
              UNIQUE INDEX uniq_article_code_tenant (code, tenant_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE catalog__articles
              ADD FULLTEXT INDEX ft_article_name (name)
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE catalog__packages (
              id BINARY(16) NOT NULL,
              tenant_id BINARY(16) NOT NULL,
              name VARCHAR(150) NOT NULL,
              code VARCHAR(20) NOT NULL,
              tax_category_code VARCHAR(50) NOT NULL,
              pricing_mode VARCHAR(20) NOT NULL,
              fixed_price_minor_units INT DEFAULT NULL,
              fixed_price_currency VARCHAR(3) DEFAULT NULL,
              status VARCHAR(20) NOT NULL,
              created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
              updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
              INDEX idx_package_tenant (tenant_id),
              INDEX idx_package_status (status),
              UNIQUE INDEX uniq_package_code_tenant (code, tenant_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE catalog__package_components (
              id BINARY(16) NOT NULL,
              package_id BINARY(16) NOT NULL,
              item_type VARCHAR(10) NOT NULL,
              item_id VARCHAR(36) NOT NULL,
              quantity INT NOT NULL,
              weight INT NOT NULL,
              INDEX idx_pkg_component_package (package_id),
              PRIMARY KEY (id),
              CONSTRAINT fk_pkg_component_package FOREIGN KEY (package_id) REFERENCES catalog__packages (id) ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE catalog__price_lists (
              id BINARY(16) NOT NULL,
              tenant_id BINARY(16) NOT NULL,
              name VARCHAR(150) NOT NULL,
              is_default TINYINT(1) NOT NULL,
              status VARCHAR(20) NOT NULL,
              created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
              updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
              INDEX idx_pricelist_tenant (tenant_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE catalog__price_list_items (
              id BINARY(16) NOT NULL,
              price_list_id BINARY(16) NOT NULL,
              item_type VARCHAR(10) NOT NULL,
              item_id VARCHAR(36) NOT NULL,
              net_price_minor_units INT NOT NULL,
              net_price_currency VARCHAR(3) NOT NULL,
              tax_category_override VARCHAR(50) DEFAULT NULL,
              INDEX idx_plitem_pricelist (price_list_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE catalog__price_rules (
              id BINARY(16) NOT NULL,
              price_list_id BINARY(16) NOT NULL,
              rule_type VARCHAR(30) NOT NULL,
              condition_json JSON NOT NULL,
              adjustment_json JSON NOT NULL,
              INDEX idx_prrule_pricelist (price_list_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE catalog__price_rules');
        $this->addSql('DROP TABLE catalog__price_list_items');
        $this->addSql('DROP TABLE catalog__price_lists');
        $this->addSql('DROP TABLE catalog__package_components');
        $this->addSql('DROP TABLE catalog__packages');
        $this->addSql('DROP TABLE catalog__articles');
        $this->addSql('DROP TABLE catalog__acts');
    }
}
