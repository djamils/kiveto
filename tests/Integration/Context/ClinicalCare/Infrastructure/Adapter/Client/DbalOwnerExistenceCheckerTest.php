<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\ClinicalCare\Infrastructure\Adapter\Client;

use App\Context\ClinicalCare\Application\Port\OwnerExistenceCheckerInterface;
use App\Context\ClinicalCare\Domain\ValueObject\OwnerId;
use App\Fixtures\Context\Client\Factory\ClientEntityFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

final class DbalOwnerExistenceCheckerTest extends KernelTestCase
{
    use Factories;

    public function testReturnsTrueWhenClientExists(): void
    {
        $clientId = '11111111-1111-4111-8111-111111111111';

        ClientEntityFactory::new()->withId($clientId)->create();

        $checker = self::getContainer()->get(OwnerExistenceCheckerInterface::class);
        \assert($checker instanceof OwnerExistenceCheckerInterface);

        self::assertTrue($checker->exists(OwnerId::fromString($clientId)));
    }

    public function testReturnsFalseWhenClientDoesNotExist(): void
    {
        $checker = self::getContainer()->get(OwnerExistenceCheckerInterface::class);
        \assert($checker instanceof OwnerExistenceCheckerInterface);

        self::assertFalse(
            $checker->exists(OwnerId::fromString('00000000-0000-4000-8000-000000000000')),
        );
    }
}
