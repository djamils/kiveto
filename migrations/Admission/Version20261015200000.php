<?php

declare(strict_types=1);

namespace DoctrineMigrations\Admission;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261015200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Admission: what the desk noted at arrival (weighing, flags, note) and the chip read for an unidentified patient.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE admission__admissions ADD arrival_check_in JSON DEFAULT NULL, ADD scanned_microchip VARCHAR(15) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE admission__admissions DROP arrival_check_in, DROP scanned_microchip');
    }
}
