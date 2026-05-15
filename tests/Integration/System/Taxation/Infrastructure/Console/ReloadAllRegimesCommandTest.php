<?php

declare(strict_types=1);

namespace App\Tests\Integration\System\Taxation\Infrastructure\Console;

use App\System\Taxation\Domain\Repository\TaxRegimeRepositoryInterface;
use App\System\Taxation\Infrastructure\Console\LoadTaxCategoriesCommand;
use App\System\Taxation\Infrastructure\Console\ReloadAllRegimesCommand;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Zenstruck\Foundry\Test\Factories;

final class ReloadAllRegimesCommandTest extends KernelTestCase
{
    use Factories;

    public function testReloadAll(): void
    {
        $categoriesCommand = static::getContainer()->get(LoadTaxCategoriesCommand::class);
        \assert($categoriesCommand instanceof LoadTaxCategoriesCommand);
        $categoryTester = new CommandTester($categoriesCommand);
        $categoryTester->execute([]);

        self::assertSame(Command::SUCCESS, $categoryTester->getStatusCode());

        $reloadCommand = static::getContainer()->get(ReloadAllRegimesCommand::class);
        \assert($reloadCommand instanceof ReloadAllRegimesCommand);
        $reloadTester = new CommandTester($reloadCommand);
        $reloadTester->execute([]);

        self::assertSame(Command::SUCCESS, $reloadTester->getStatusCode());

        $allActive = $this->getRegimeRepository()->findAllActive();

        self::assertCount(1, $allActive);
        self::assertSame('FR', $allActive[0]->id()->toString());
        self::assertCount(4, $allActive[0]->rates());
    }

    public function testReloadAllIsIdempotent(): void
    {
        $categoriesCommand = static::getContainer()->get(LoadTaxCategoriesCommand::class);
        \assert($categoriesCommand instanceof LoadTaxCategoriesCommand);
        (new CommandTester($categoriesCommand))->execute([]);

        $reloadCommand = static::getContainer()->get(ReloadAllRegimesCommand::class);
        \assert($reloadCommand instanceof ReloadAllRegimesCommand);

        $tester = new CommandTester($reloadCommand);
        $tester->execute([]);
        self::assertSame(Command::SUCCESS, $tester->getStatusCode());

        $tester->execute([]);
        self::assertSame(Command::SUCCESS, $tester->getStatusCode());

        $allActive = $this->getRegimeRepository()->findAllActive();

        self::assertCount(1, $allActive);
        self::assertSame('FR', $allActive[0]->id()->toString());
    }

    public function testReloadAllOutputsConfirmation(): void
    {
        $categoriesCommand = static::getContainer()->get(LoadTaxCategoriesCommand::class);
        \assert($categoriesCommand instanceof LoadTaxCategoriesCommand);
        (new CommandTester($categoriesCommand))->execute([]);

        $reloadCommand = static::getContainer()->get(ReloadAllRegimesCommand::class);
        \assert($reloadCommand instanceof ReloadAllRegimesCommand);

        $tester = new CommandTester($reloadCommand);
        $tester->execute([]);

        self::assertStringContainsString('Reloaded', $tester->getDisplay());
    }

    private function getRegimeRepository(): TaxRegimeRepositoryInterface
    {
        $repo = static::getContainer()->get(TaxRegimeRepositoryInterface::class);
        \assert($repo instanceof TaxRegimeRepositoryInterface);

        return $repo;
    }
}
