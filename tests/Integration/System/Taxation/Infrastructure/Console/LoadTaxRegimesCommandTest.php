<?php

declare(strict_types=1);

namespace App\Tests\Integration\System\Taxation\Infrastructure\Console;

use App\System\Taxation\Domain\Repository\TaxRegimeRepositoryInterface;
use App\System\Taxation\Domain\ValueObject\TaxRegimeId;
use App\System\Taxation\Infrastructure\Console\LoadTaxCategoriesCommand;
use App\System\Taxation\Infrastructure\Console\LoadTaxRegimeCommand;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Zenstruck\Foundry\Test\Factories;

final class LoadTaxRegimesCommandTest extends KernelTestCase
{
    use Factories;

    public function testLoadCategoriesAndFrRegime(): void
    {
        $categoriesCommand = static::getContainer()->get(LoadTaxCategoriesCommand::class);
        \assert($categoriesCommand instanceof LoadTaxCategoriesCommand);
        $categoryTester = new CommandTester($categoriesCommand);
        $categoryTester->execute([]);

        self::assertSame(Command::SUCCESS, $categoryTester->getStatusCode());

        $regimeCommand = static::getContainer()->get(LoadTaxRegimeCommand::class);
        \assert($regimeCommand instanceof LoadTaxRegimeCommand);
        $regimeTester = new CommandTester($regimeCommand);
        $regimeTester->execute(['regimeId' => 'FR']);

        self::assertSame(Command::SUCCESS, $regimeTester->getStatusCode());

        $regime = $this->getRegimeRepository()->findById(TaxRegimeId::fromString('FR'));

        self::assertNotNull($regime);
        self::assertSame('FR', $regime->id()->toString());
        self::assertSame('Régime TVA France', $regime->name());
        self::assertCount(4, $regime->rates());
    }

    public function testLoadRegimeIdempotent(): void
    {
        $categoriesCommand = static::getContainer()->get(LoadTaxCategoriesCommand::class);
        \assert($categoriesCommand instanceof LoadTaxCategoriesCommand);
        $categoryTester = new CommandTester($categoriesCommand);
        $categoryTester->execute([]);

        self::assertSame(Command::SUCCESS, $categoryTester->getStatusCode());

        $regimeCommand = static::getContainer()->get(LoadTaxRegimeCommand::class);
        \assert($regimeCommand instanceof LoadTaxRegimeCommand);
        $regimeTester = new CommandTester($regimeCommand);

        $regimeTester->execute(['regimeId' => 'FR']);
        self::assertSame(Command::SUCCESS, $regimeTester->getStatusCode());

        $regimeTester->execute(['regimeId' => 'FR']);
        self::assertSame(Command::SUCCESS, $regimeTester->getStatusCode());

        $regime = $this->getRegimeRepository()->findById(TaxRegimeId::fromString('FR'));

        self::assertNotNull($regime);
        self::assertCount(4, $regime->rates());
    }

    public function testLoadCategoriesCommandClass(): void
    {
        $command = static::getContainer()->get(LoadTaxCategoriesCommand::class);
        \assert($command instanceof LoadTaxCategoriesCommand);

        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Loaded', $tester->getDisplay());
    }

    public function testLoadRegimeCommandClass(): void
    {
        $categoriesCommand = static::getContainer()->get(LoadTaxCategoriesCommand::class);
        \assert($categoriesCommand instanceof LoadTaxCategoriesCommand);
        (new CommandTester($categoriesCommand))->execute([]);

        $command = static::getContainer()->get(LoadTaxRegimeCommand::class);
        \assert($command instanceof LoadTaxRegimeCommand);

        $tester = new CommandTester($command);
        $tester->execute(['regimeId' => 'FR']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('FR', $tester->getDisplay());
    }

    private function getRegimeRepository(): TaxRegimeRepositoryInterface
    {
        $repo = static::getContainer()->get(TaxRegimeRepositoryInterface::class);
        \assert($repo instanceof TaxRegimeRepositoryInterface);

        return $repo;
    }
}
