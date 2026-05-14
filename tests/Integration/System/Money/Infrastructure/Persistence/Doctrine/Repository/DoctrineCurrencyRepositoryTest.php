<?php

declare(strict_types=1);

namespace App\Tests\Integration\System\Money\Infrastructure\Persistence\Doctrine\Repository;

use App\Fixtures\System\Money\Factory\CurrencyEntityFactory;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\System\Money\Domain\Currency;
use App\System\Money\Domain\Repository\CurrencyRepository;
use App\System\Money\Domain\ValueObject\CurrencyDecimals;
use App\System\Money\Domain\ValueObject\CurrencySymbol;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

final class DoctrineCurrencyRepositoryTest extends KernelTestCase
{
    use Factories;

    public function testSaveAndFindByCode(): void
    {
        $repository = $this->getRepository();
        $now        = new \DateTimeImmutable('2026-05-14T10:00:00+00:00');

        $currency = Currency::reconstitute(
            code: CurrencyCode::fromString('EUR'),
            symbol: CurrencySymbol::fromString('€'),
            decimals: CurrencyDecimals::of(2),
            displayName: 'Euro',
            active: true,
            createdAt: $now,
            updatedAt: $now,
        );

        $repository->save($currency);

        $found = $repository->findByCode(CurrencyCode::fromString('EUR'));

        self::assertNotNull($found);
        self::assertSame('EUR', $found->code()->toString());
        self::assertSame('€', $found->symbol()->toString());
        self::assertSame(2, $found->decimals()->value());
        self::assertSame('Euro', $found->displayName());
        self::assertTrue($found->isActive());
    }

    public function testSaveUpdatesExistingCurrency(): void
    {
        $repository = $this->getRepository();
        $now        = new \DateTimeImmutable('2026-05-14T10:00:00+00:00');

        $currency = Currency::reconstitute(
            code: CurrencyCode::fromString('EUR'),
            symbol: CurrencySymbol::fromString('€'),
            decimals: CurrencyDecimals::of(2),
            displayName: 'Euro',
            active: true,
            createdAt: $now,
            updatedAt: $now,
        );

        $repository->save($currency);

        // Update the display
        $currency->updateDisplay(
            CurrencySymbol::fromString('€'),
            CurrencyDecimals::of(2),
            'Euro Updated',
        );
        $repository->save($currency);

        $found = $repository->findByCode(CurrencyCode::fromString('EUR'));

        self::assertNotNull($found);
        self::assertSame('Euro Updated', $found->displayName());
    }

    public function testFindByCodeReturnsNullWhenNotFound(): void
    {
        $repository = $this->getRepository();

        $result = $repository->findByCode(CurrencyCode::fromString('XYZ'));

        self::assertNull($result);
    }

    public function testSaveAndFindByCodeUsingFactory(): void
    {
        CurrencyEntityFactory::createOne([
            'code'        => 'USD',
            'symbol'      => '$',
            'decimals'    => 2,
            'displayName' => 'US Dollar',
            'active'      => true,
        ]);

        $repository = $this->getRepository();
        $found      = $repository->findByCode(CurrencyCode::fromString('USD'));

        self::assertNotNull($found);
        self::assertSame('USD', $found->code()->toString());
    }

    private function getRepository(): CurrencyRepository
    {
        $repository = static::getContainer()->get(CurrencyRepository::class);
        \assert($repository instanceof CurrencyRepository);

        return $repository;
    }
}
