<?php

declare(strict_types=1);

namespace App\Fixtures\System\Money\Factory;

use App\System\Money\Infrastructure\Persistence\Doctrine\Entity\ExchangeRateEntity;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<ExchangeRateEntity>
 */
final class ExchangeRateEntityFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return ExchangeRateEntity::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'id'            => Uuid::v7(),
            'currencyFrom'  => 'EUR',
            'currencyTo'    => 'USD',
            'rate'          => '1.0800',
            'effectiveDate' => new \DateTimeImmutable('2026-01-01'),
            'source'        => 'ECB',
            'createdAt'     => new \DateTimeImmutable(),
        ];
    }
}
