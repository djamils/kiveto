<?php

declare(strict_types=1);

namespace App\Tests\Integration\System\Money\Infrastructure\Persistence\Doctrine\Repository;

use App\Fixtures\System\Money\Factory\CurrencyEntityFactory;
use App\Fixtures\System\Money\Factory\ExchangeRateEntityFactory;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\System\Money\Domain\ExchangeRate;
use App\System\Money\Domain\Repository\ExchangeRateRepository;
use App\System\Money\Domain\ValueObject\ExchangeRateId;
use App\System\Money\Domain\ValueObject\ExchangeRatePair;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;

final class DoctrineExchangeRateRepositoryTest extends KernelTestCase
{
    use Factories;

    public function testSaveAndFindRateAt(): void
    {
        // Create required currencies first (FK constraint)
        CurrencyEntityFactory::createOne(['code' => 'EUR', 'symbol' => '€', 'decimals' => 2, 'displayName' => 'Euro']);
        CurrencyEntityFactory::createOne(['code' => 'CHF', 'symbol' => 'CHF', 'decimals' => 2, 'displayName' => 'Swiss Franc']);

        $repository    = $this->getRepository();
        $now           = new \DateTimeImmutable('2026-05-14T10:00:00+00:00');
        $effectiveDate = new \DateTimeImmutable('2026-05-14');

        $exchangeRate = ExchangeRate::create(
            id: ExchangeRateId::fromString('550e8400-e29b-41d4-a716-446655440001'),
            pair: ExchangeRatePair::of(
                CurrencyCode::fromString('EUR'),
                CurrencyCode::fromString('CHF'),
            ),
            rate: '0.9523',
            effectiveDate: $effectiveDate,
            source: 'ECB',
            createdAt: $now,
        );

        $repository->save($exchangeRate);

        $pair  = ExchangeRatePair::of(CurrencyCode::fromString('EUR'), CurrencyCode::fromString('CHF'));
        $found = $repository->findRateAt($pair, $effectiveDate);

        self::assertNotNull($found);
        self::assertSame('EUR/CHF', $found->pair()->toString());
        self::assertSame('ECB', $found->source());
    }

    public function testFindRateAtReturnsMostRecentOnOrBeforeDate(): void
    {
        CurrencyEntityFactory::createOne(['code' => 'EUR', 'symbol' => '€', 'decimals' => 2, 'displayName' => 'Euro']);
        CurrencyEntityFactory::createOne(['code' => 'USD', 'symbol' => '$', 'decimals' => 2, 'displayName' => 'US Dollar']);

        $repository = $this->getRepository();
        $now        = new \DateTimeImmutable('2026-05-14T10:00:00+00:00');

        ExchangeRateEntityFactory::createOne([
            'id'            => Uuid::fromString('550e8400-e29b-41d4-a716-446655440001'),
            'currencyFrom'  => 'EUR',
            'currencyTo'    => 'USD',
            'rate'          => '1.0800',
            'effectiveDate' => new \DateTimeImmutable('2026-05-10'),
            'source'        => 'ECB',
            'createdAt'     => $now,
        ]);

        ExchangeRateEntityFactory::createOne([
            'id'            => Uuid::fromString('550e8400-e29b-41d4-a716-446655440002'),
            'currencyFrom'  => 'EUR',
            'currencyTo'    => 'USD',
            'rate'          => '1.0900',
            'effectiveDate' => new \DateTimeImmutable('2026-05-12'),
            'source'        => 'ECB',
            'createdAt'     => $now,
        ]);

        $pair = ExchangeRatePair::of(CurrencyCode::fromString('EUR'), CurrencyCode::fromString('USD'));

        // Should return the most recent rate on or before May 14
        $found = $repository->findRateAt($pair, new \DateTimeImmutable('2026-05-14'));

        self::assertNotNull($found);
        self::assertSame('2026-05-12', $found->effectiveDate()->format('Y-m-d'));
    }

    public function testFindRateAtReturnsNullWhenNotFound(): void
    {
        $repository = $this->getRepository();

        $pair   = ExchangeRatePair::of(CurrencyCode::fromString('EUR'), CurrencyCode::fromString('JPY'));
        $result = $repository->findRateAt($pair, new \DateTimeImmutable('2026-05-14'));

        self::assertNull($result);
    }

    private function getRepository(): ExchangeRateRepository
    {
        $repository = static::getContainer()->get(ExchangeRateRepository::class);
        \assert($repository instanceof ExchangeRateRepository);

        return $repository;
    }
}
