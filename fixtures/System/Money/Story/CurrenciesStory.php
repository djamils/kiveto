<?php

declare(strict_types=1);

namespace App\Fixtures\System\Money\Story;

use App\Fixtures\System\Money\Factory\CurrencyEntityFactory;
use Zenstruck\Foundry\Story;

final class CurrenciesStory extends Story
{
    public function build(): void
    {
        $now = new \DateTimeImmutable();

        $currencies = [
            ['code' => 'EUR', 'symbol' => '€',   'decimals' => 2, 'displayName' => 'Euro',              'active' => true],
            ['code' => 'CHF', 'symbol' => 'CHF',  'decimals' => 2, 'displayName' => 'Franc suisse',       'active' => true],
            ['code' => 'GBP', 'symbol' => '£',   'decimals' => 2, 'displayName' => 'Livre sterling',     'active' => true],
            ['code' => 'USD', 'symbol' => '$',   'decimals' => 2, 'displayName' => 'Dollar américain',   'active' => true],
        ];

        foreach ($currencies as $data) {
            CurrencyEntityFactory::createOne([
                'code'        => $data['code'],
                'symbol'      => $data['symbol'],
                'decimals'    => $data['decimals'],
                'displayName' => $data['displayName'],
                'active'      => $data['active'],
                'createdAt'   => $now,
                'updatedAt'   => $now,
            ]);
        }
    }
}
