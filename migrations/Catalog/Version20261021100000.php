<?php

declare(strict_types=1);

namespace DoctrineMigrations\Catalog;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261021100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Catalogue prices keep the mode they were entered in (incl / excl tax); existing prices were stored excluding tax.';
    }

    public function up(Schema $schema): void
    {
        // Existing rows hold amounts the page converted to excluding tax: they become 'excl'.
        $this->addSql("ALTER TABLE catalog__acts ADD base_price_mode VARCHAR(4) NOT NULL DEFAULT 'excl' AFTER base_price_currency");
        $this->addSql('ALTER TABLE catalog__acts ALTER base_price_mode DROP DEFAULT');

        $this->addSql("ALTER TABLE catalog__articles ADD base_price_mode VARCHAR(4) NOT NULL DEFAULT 'excl' AFTER base_price_currency");
        $this->addSql('ALTER TABLE catalog__articles ALTER base_price_mode DROP DEFAULT');

        // A package priced as the sum of its components has no price, hence no mode.
        $this->addSql('ALTER TABLE catalog__packages ADD fixed_price_mode VARCHAR(4) DEFAULT NULL AFTER fixed_price_currency');
        $this->addSql("UPDATE catalog__packages SET fixed_price_mode = 'excl' WHERE fixed_price_minor_units IS NOT NULL");

        // A price list's price is no longer "net": it is the price as entered, in its mode.
        $this->addSql('ALTER TABLE catalog__price_list_items RENAME COLUMN net_price_minor_units TO price_minor_units');
        $this->addSql('ALTER TABLE catalog__price_list_items RENAME COLUMN net_price_currency TO price_currency');
        $this->addSql("ALTER TABLE catalog__price_list_items ADD price_mode VARCHAR(4) NOT NULL DEFAULT 'excl' AFTER price_currency");
        $this->addSql('ALTER TABLE catalog__price_list_items ALTER price_mode DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE catalog__price_list_items DROP price_mode');
        $this->addSql('ALTER TABLE catalog__price_list_items RENAME COLUMN price_currency TO net_price_currency');
        $this->addSql('ALTER TABLE catalog__price_list_items RENAME COLUMN price_minor_units TO net_price_minor_units');
        $this->addSql('ALTER TABLE catalog__packages DROP fixed_price_mode');
        $this->addSql('ALTER TABLE catalog__articles DROP base_price_mode');
        $this->addSql('ALTER TABLE catalog__acts DROP base_price_mode');
    }
}
