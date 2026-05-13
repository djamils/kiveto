<?php

declare(strict_types=1);

namespace App\System\Money\Infrastructure\ExchangeRate;

use App\System\Money\Application\Port\ExchangeRateProvider;
use App\System\Money\Domain\ValueObject\ExchangeRatePair;
use App\System\Money\Domain\ValueObject\HistoricalRate;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class CachedExchangeRateProvider implements ExchangeRateProvider
{
    public function __construct(
        private readonly ExchangeRateProvider $inner,
        private readonly CacheInterface $cache,
        private readonly int $ttl = 3600,
    ) {
    }

    public function getCurrentRate(ExchangeRatePair $pair): HistoricalRate
    {
        $key = \sprintf('ecb_rate_%s_%s_current', $pair->from()->toString(), $pair->to()->toString());

        return $this->cache->get($key, function (ItemInterface $item) use ($pair): HistoricalRate {
            $item->expiresAfter($this->ttl);

            return $this->inner->getCurrentRate($pair);
        });
    }

    public function getRateAt(ExchangeRatePair $pair, \DateTimeImmutable $date): HistoricalRate
    {
        $key = \sprintf(
            'ecb_rate_%s_%s_%s',
            $pair->from()->toString(),
            $pair->to()->toString(),
            $date->format('Y-m-d'),
        );

        return $this->cache->get($key, function (ItemInterface $item) use ($pair, $date): HistoricalRate {
            $item->expiresAfter($this->ttl);

            return $this->inner->getRateAt($pair, $date);
        });
    }

    /** @return list<HistoricalRate> */
    public function fetchSnapshot(\DateTimeImmutable $date): array
    {
        $key = \sprintf('ecb_snapshot_%s', $date->format('Y-m-d'));

        return $this->cache->get($key, function (ItemInterface $item) use ($date): array {
            $item->expiresAfter($this->ttl);

            return $this->inner->fetchSnapshot($date);
        });
    }
}
