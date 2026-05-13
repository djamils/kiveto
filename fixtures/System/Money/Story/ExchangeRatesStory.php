<?php

declare(strict_types=1);

namespace App\Fixtures\System\Money\Story;

use App\Fixtures\System\Money\Factory\ExchangeRateEntityFactory;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Story;

final class ExchangeRatesStory extends Story
{
    public function build(): void
    {
        $historical = new \DateTimeImmutable('2026-01-02');
        $recent     = new \DateTimeImmutable('2026-05-01');
        $now        = new \DateTimeImmutable();

        $rates = [
            ['from' => 'EUR', 'to' => 'CHF', 'rate' => '0.9400', 'date' => $historical],
            ['from' => 'EUR', 'to' => 'CHF', 'rate' => '0.9523', 'date' => $recent],
            ['from' => 'EUR', 'to' => 'GBP', 'rate' => '0.8450', 'date' => $historical],
            ['from' => 'EUR', 'to' => 'GBP', 'rate' => '0.8600', 'date' => $recent],
            ['from' => 'EUR', 'to' => 'USD', 'rate' => '1.0750', 'date' => $historical],
            ['from' => 'EUR', 'to' => 'USD', 'rate' => '1.0850', 'date' => $recent],
        ];

        foreach ($rates as $data) {
            ExchangeRateEntityFactory::createOne([
                'id'            => Uuid::v7(),
                'currencyFrom'  => $data['from'],
                'currencyTo'    => $data['to'],
                'rate'          => $data['rate'],
                'effectiveDate' => $data['date'],
                'source'        => 'ECB',
                'createdAt'     => $now,
            ]);
        }
    }
}
