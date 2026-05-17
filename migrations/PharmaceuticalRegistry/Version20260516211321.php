<?php

declare(strict_types=1);

namespace DoctrineMigrations\PharmaceuticalRegistry;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260516211321 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create PharmaceuticalRegistry tables (9 tables) with FULLTEXT index on commercial_name.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE pharmaceutical_registry__active_substances (
              id BINARY(16) NOT NULL,
              label VARCHAR(255) NOT NULL,
              label_normalized VARCHAR(255) NOT NULL,
              inn_code VARCHAR(64) DEFAULT NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME NOT NULL,
              UNIQUE INDEX uniq_pharma_active_substance_label (label_normalized),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE pharmaceutical_registry__authorizations (
              id BINARY(16) NOT NULL,
              commercial_name VARCHAR(255) NOT NULL,
              holder_laboratory VARCHAR(255) NOT NULL,
              status VARCHAR(48) NOT NULL,
              authorization_date DATE NOT NULL,
              nature VARCHAR(32) NOT NULL,
              pharmaceutical_form VARCHAR(128) NOT NULL,
              atc_vet_code VARCHAR(16) DEFAULT NULL,
              permanent_identifier VARCHAR(12) DEFAULT NULL,
              controlled_substance_class VARCHAR(32) DEFAULT NULL,
              controlled_substance_jurisdiction VARCHAR(3) DEFAULT NULL,
              controlled_substance_code VARCHAR(32) DEFAULT NULL,
              controlled_substance_restrictions LONGTEXT DEFAULT NULL,
              content_hash CHAR(64) NOT NULL,
              last_import_source VARCHAR(16) NOT NULL,
              last_imported_at DATETIME NOT NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME NOT NULL,
              INDEX idx_pharma_authz_status (status),
              INDEX idx_pharma_authz_atc_vet (atc_vet_code),
              INDEX idx_pharma_authz_perm_id (permanent_identifier),
              INDEX idx_pharma_authz_last_imported (last_imported_at),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE pharmaceutical_registry__compositions (
              id BINARY(16) NOT NULL,
              quantity_value VARCHAR(32) DEFAULT NULL,
              quantity_unit_label VARCHAR(255) DEFAULT NULL,
              quantity_unit_code VARCHAR(32) DEFAULT NULL,
              is_excipient TINYINT DEFAULT 0 NOT NULL,
              authorization_id BINARY(16) NOT NULL,
              active_substance_id BINARY(16) NOT NULL,
              INDEX IDX_5C3C80902F8B0EB2 (authorization_id),
              INDEX IDX_5C3C8090265D87F5 (active_substance_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE pharmaceutical_registry__jurisdictions (
              id BINARY(16) NOT NULL,
              jurisdiction_code VARCHAR(3) NOT NULL,
              authority_code VARCHAR(16) NOT NULL,
              authority_identifier VARCHAR(64) NOT NULL,
              authorization_id BINARY(16) NOT NULL,
              INDEX IDX_DBE44252F8B0EB2 (authorization_id),
              UNIQUE INDEX uniq_pharma_jurisdiction (
                jurisdiction_code, authority_code,
                authority_identifier
              ),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE pharmaceutical_registry__presentations (
              id BINARY(16) NOT NULL,
              description VARCHAR(255) NOT NULL,
              unit_count VARCHAR(20) DEFAULT NULL,
              packaging VARCHAR(255) DEFAULT NULL,
              gtin VARCHAR(14) DEFAULT NULL,
              eu_pack_identifier VARCHAR(36) DEFAULT NULL,
              prescription_required TINYINT DEFAULT 0 NOT NULL,
              prescription_class VARCHAR(32) NOT NULL,
              prescription_retention_years INT DEFAULT NULL,
              prescription_juris_jurisdiction VARCHAR(3) DEFAULT NULL,
              prescription_juris_code VARCHAR(64) DEFAULT NULL,
              authorization_id BINARY(16) NOT NULL,
              INDEX IDX_45E4D6092F8B0EB2 (authorization_id),
              INDEX idx_pharma_presentation_gtin (gtin),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE pharmaceutical_registry__snapshot_entries (
              id BINARY(16) NOT NULL,
              authority_identifier VARCHAR(64) NOT NULL,
              content_hash CHAR(64) NOT NULL,
              raw_dto JSON NOT NULL,
              diff_kind VARCHAR(16) DEFAULT NULL,
              target_uuid BINARY(16) DEFAULT NULL,
              changes JSON DEFAULT NULL,
              snapshot_id BINARY(16) NOT NULL,
              INDEX IDX_92A9588E7B39395E (snapshot_id),
              INDEX idx_pharma_snapshot_entry_authority (authority_identifier),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE pharmaceutical_registry__snapshots (
              id BINARY(16) NOT NULL,
              source VARCHAR(16) NOT NULL,
              status VARCHAR(16) NOT NULL,
              downloaded_at DATETIME NOT NULL,
              applied_at DATETIME DEFAULT NULL,
              error_message LONGTEXT DEFAULT NULL,
              INDEX idx_pharma_snapshot_source_date (source, downloaded_at),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE pharmaceutical_registry__summaries (
              id BINARY(16) NOT NULL,
              sections JSON NOT NULL,
              source_url VARCHAR(512) DEFAULT NULL,
              last_updated_at DATETIME DEFAULT NULL,
              authorization_id BINARY(16) NOT NULL,
              UNIQUE INDEX UNIQ_64BCE9742F8B0EB2 (authorization_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE pharmaceutical_registry__target_usages (
              id BINARY(16) NOT NULL,
              administration_route VARCHAR(32) NOT NULL,
              target_species_code VARCHAR(34) NOT NULL,
              food_production_purpose VARCHAR(32) DEFAULT NULL,
              withdrawal_period_value INT DEFAULT NULL,
              withdrawal_period_unit VARCHAR(8) DEFAULT NULL,
              jurisdictional_note LONGTEXT DEFAULT NULL,
              authorization_id BINARY(16) NOT NULL,
              INDEX IDX_75A60FCD2F8B0EB2 (authorization_id),
              INDEX idx_pharma_target_usage_species (target_species_code),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              pharmaceutical_registry__compositions
            ADD
              CONSTRAINT FK_5C3C80902F8B0EB2 FOREIGN KEY (authorization_id) REFERENCES pharmaceutical_registry__authorizations (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              pharmaceutical_registry__compositions
            ADD
              CONSTRAINT FK_5C3C8090265D87F5 FOREIGN KEY (active_substance_id) REFERENCES pharmaceutical_registry__active_substances (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              pharmaceutical_registry__jurisdictions
            ADD
              CONSTRAINT FK_DBE44252F8B0EB2 FOREIGN KEY (authorization_id) REFERENCES pharmaceutical_registry__authorizations (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              pharmaceutical_registry__presentations
            ADD
              CONSTRAINT FK_45E4D6092F8B0EB2 FOREIGN KEY (authorization_id) REFERENCES pharmaceutical_registry__authorizations (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              pharmaceutical_registry__snapshot_entries
            ADD
              CONSTRAINT FK_92A9588E7B39395E FOREIGN KEY (snapshot_id) REFERENCES pharmaceutical_registry__snapshots (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              pharmaceutical_registry__summaries
            ADD
              CONSTRAINT FK_64BCE9742F8B0EB2 FOREIGN KEY (authorization_id) REFERENCES pharmaceutical_registry__authorizations (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              pharmaceutical_registry__target_usages
            ADD
              CONSTRAINT FK_75A60FCD2F8B0EB2 FOREIGN KEY (authorization_id) REFERENCES pharmaceutical_registry__authorizations (id) ON DELETE CASCADE
        SQL);
        // FULLTEXT index must be added manually — Doctrine ORM has no native FULLTEXT support
        $this->addSql('ALTER TABLE pharmaceutical_registry__authorizations ADD FULLTEXT INDEX ft_pharma_authz_name (commercial_name)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pharmaceutical_registry__compositions DROP FOREIGN KEY FK_5C3C80902F8B0EB2');
        $this->addSql('ALTER TABLE pharmaceutical_registry__compositions DROP FOREIGN KEY FK_5C3C8090265D87F5');
        $this->addSql('ALTER TABLE pharmaceutical_registry__jurisdictions DROP FOREIGN KEY FK_DBE44252F8B0EB2');
        $this->addSql('ALTER TABLE pharmaceutical_registry__presentations DROP FOREIGN KEY FK_45E4D6092F8B0EB2');
        $this->addSql('ALTER TABLE pharmaceutical_registry__snapshot_entries DROP FOREIGN KEY FK_92A9588E7B39395E');
        $this->addSql('ALTER TABLE pharmaceutical_registry__summaries DROP FOREIGN KEY FK_64BCE9742F8B0EB2');
        $this->addSql('ALTER TABLE pharmaceutical_registry__target_usages DROP FOREIGN KEY FK_75A60FCD2F8B0EB2');
        $this->addSql('DROP TABLE pharmaceutical_registry__active_substances');
        $this->addSql('DROP TABLE pharmaceutical_registry__authorizations');
        $this->addSql('DROP TABLE pharmaceutical_registry__compositions');
        $this->addSql('DROP TABLE pharmaceutical_registry__jurisdictions');
        $this->addSql('DROP TABLE pharmaceutical_registry__presentations');
        $this->addSql('DROP TABLE pharmaceutical_registry__snapshot_entries');
        $this->addSql('DROP TABLE pharmaceutical_registry__snapshots');
        $this->addSql('DROP TABLE pharmaceutical_registry__summaries');
        $this->addSql('DROP TABLE pharmaceutical_registry__target_usages');
        $this->addSql('ALTER TABLE pharmaceutical_registry__authorizations DROP INDEX ft_pharma_authz_name');
    }
}
