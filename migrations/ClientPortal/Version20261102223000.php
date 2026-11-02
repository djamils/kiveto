<?php

declare(strict_types=1);

namespace DoctrineMigrations\ClientPortal;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261102223000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'ClientPortal: the animal a « new pet » booking registered, by request marker (S4B-01).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE client_portal__booked_pet_markers (
              marker CHAR(64) NOT NULL,
              animal_id BINARY(16) NOT NULL,
              created_at DATETIME NOT NULL,
              INDEX idx_booked_pet_marker_created (created_at),
              PRIMARY KEY (marker)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE client_portal__booked_pet_markers');
    }
}
