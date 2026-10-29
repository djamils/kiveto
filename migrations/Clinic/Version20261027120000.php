<?php

declare(strict_types=1);

namespace DoctrineMigrations\Clinic;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261027120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Let a clinic choose its SMS sender name and its SMS overage cap.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE clinic__clinics ADD messaging_settings JSON NOT NULL');

        // An empty object reads back as "not chosen": the defaults derived from the name.
        $this->addSql("UPDATE clinic__clinics SET messaging_settings = '{}'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE clinic__clinics DROP messaging_settings');
    }
}
