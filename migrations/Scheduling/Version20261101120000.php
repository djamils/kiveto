<?php

declare(strict_types=1);

namespace DoctrineMigrations\Scheduling;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261101120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Scheduling: the animal registered on the Portal for a booking (with-new-pet), archived on decline or expiry (S4g).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE scheduling__appointments ADD created_pet_id BINARY(16) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE scheduling__appointments DROP created_pet_id');
    }
}
