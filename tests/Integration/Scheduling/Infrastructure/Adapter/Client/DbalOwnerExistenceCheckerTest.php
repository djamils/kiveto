<?php

declare(strict_types=1);

namespace App\Tests\Integration\Scheduling\Infrastructure\Adapter\Client;

use App\Fixtures\Client\Factory\ClientEntityFactory;
use App\Scheduling\Application\Port\OwnerExistenceCheckerInterface;
use App\Scheduling\Domain\ValueObject\OwnerId;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

final class DbalOwnerExistenceCheckerTest extends KernelTestCase
{
    use Factories;

    public function testReturnsTrueWhenClientExists(): void
    {
        $clientId = '01234567-89ab-cdef-0123-456789abcdef';

        ClientEntityFactory::new()
            ->withId($clientId)
            ->create()
        ;

        $checker = self::getContainer()->get(OwnerExistenceCheckerInterface::class);
        \assert($checker instanceof OwnerExistenceCheckerInterface);

        self::assertTrue($checker->exists(OwnerId::fromString($clientId)));
    }

    public function testReturnsFalseWhenClientDoesNotExist(): void
    {
        $checker = self::getContainer()->get(OwnerExistenceCheckerInterface::class);
        \assert($checker instanceof OwnerExistenceCheckerInterface);

        self::assertFalse(
            $checker->exists(OwnerId::fromString('00000000-0000-0000-0000-000000000000')),
        );
    }
}
