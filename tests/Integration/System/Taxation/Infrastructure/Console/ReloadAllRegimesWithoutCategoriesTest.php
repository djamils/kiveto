<?php

declare(strict_types=1);

namespace App\Tests\Integration\System\Taxation\Infrastructure\Console;

use App\System\Taxation\Domain\Exception\UnknownCategoryPatternException;
use App\System\Taxation\Infrastructure\Console\ReloadAllRegimesCommand;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Zenstruck\Foundry\Test\Factories;

final class ReloadAllRegimesWithoutCategoriesTest extends KernelTestCase
{
    use Factories;

    public function testReloadWithoutCategoriesThrows(): void
    {
        $reloadCommand = static::getContainer()->get(ReloadAllRegimesCommand::class);
        \assert($reloadCommand instanceof ReloadAllRegimesCommand);

        $tester = new CommandTester($reloadCommand);

        $this->expectException(UnknownCategoryPatternException::class);

        $tester->execute([], ['catch_exceptions' => false]);
    }
}
