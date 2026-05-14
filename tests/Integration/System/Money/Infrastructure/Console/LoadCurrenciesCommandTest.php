<?php

declare(strict_types=1);

namespace App\Tests\Integration\System\Money\Infrastructure\Console;

use App\Shared\Domain\ValueObject\CurrencyCode;
use App\System\Money\Domain\Repository\CurrencyRepository;
use App\System\Money\Infrastructure\Console\LoadCurrenciesCommand;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Zenstruck\Foundry\Test\Factories;

final class LoadCurrenciesCommandTest extends KernelTestCase
{
    use Factories;

    public function testLoadCurrenciesCreatesExpectedCurrencies(): void
    {
        $command = static::getContainer()->get(LoadCurrenciesCommand::class);
        \assert($command instanceof LoadCurrenciesCommand);

        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());

        $repository = static::getContainer()->get(CurrencyRepository::class);
        \assert($repository instanceof CurrencyRepository);

        self::assertNotNull($repository->findByCode(CurrencyCode::fromString('EUR')));
        self::assertNotNull($repository->findByCode(CurrencyCode::fromString('CHF')));
        self::assertNotNull($repository->findByCode(CurrencyCode::fromString('GBP')));
        self::assertNotNull($repository->findByCode(CurrencyCode::fromString('USD')));
    }

    public function testLoadCurrenciesIsIdempotent(): void
    {
        $command = static::getContainer()->get(LoadCurrenciesCommand::class);
        \assert($command instanceof LoadCurrenciesCommand);

        $tester = new CommandTester($command);

        $tester->execute([]);
        self::assertSame(Command::SUCCESS, $tester->getStatusCode());

        $tester->execute([]);
        self::assertSame(Command::SUCCESS, $tester->getStatusCode());

        $repository = static::getContainer()->get(CurrencyRepository::class);
        \assert($repository instanceof CurrencyRepository);

        self::assertNotNull($repository->findByCode(CurrencyCode::fromString('EUR')));
        self::assertNotNull($repository->findByCode(CurrencyCode::fromString('CHF')));
        self::assertNotNull($repository->findByCode(CurrencyCode::fromString('GBP')));
        self::assertNotNull($repository->findByCode(CurrencyCode::fromString('USD')));
    }
}
