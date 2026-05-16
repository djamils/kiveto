<?php

declare(strict_types=1);

namespace DoctrineMigrations\Scheduling;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260501120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Restore owner_id and animal_id columns on scheduling__appointments';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE scheduling__appointments'
                . ' ADD owner_id BINARY(16) DEFAULT NULL, ADD animal_id BINARY(16) DEFAULT NULL',
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE scheduling__appointments DROP COLUMN owner_id, DROP COLUMN animal_id');
    }
}
