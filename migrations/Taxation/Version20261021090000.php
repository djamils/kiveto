<?php

declare(strict_types=1);

namespace DoctrineMigrations\Taxation;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261021090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Nature (goods / services) of every tax category.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE taxation__tax_categories ADD nature VARCHAR(16) NOT NULL DEFAULT 'services' AFTER description");
        $this->addSql(<<<'SQL'
            UPDATE taxation__tax_categories
               SET nature = CASE
                   WHEN code LIKE 'veterinary.drug.%' OR code LIKE 'veterinary.food.%' OR code LIKE 'veterinary.consumable.%' THEN 'goods'
                   ELSE 'services'
               END
        SQL);
        $this->addSql('ALTER TABLE taxation__tax_categories ALTER nature DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE taxation__tax_categories DROP nature');
    }
}
