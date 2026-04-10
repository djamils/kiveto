<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Clinic\Infrastructure\Adapter\IdentityAccess;

use App\Context\Clinic\Application\Port\UserExistenceCheckerInterface;
use App\Fixtures\System\IdentityAccess\Factory\ClinicUserFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

final class DbalUserExistenceCheckerTest extends KernelTestCase
{
    use Factories;

    public function testReturnsTrueWhenUserExists(): void
    {
        $user = ClinicUserFactory::createOne();

        self::assertTrue($this->checker()->exists($user->getId()->toRfc4122()));
    }

    public function testReturnsFalseWhenUserDoesNotExist(): void
    {
        self::assertFalse($this->checker()->exists('01912345-6789-7abc-8def-000000000099'));
    }

    private function checker(): UserExistenceCheckerInterface
    {
        $checker = self::getContainer()->get(UserExistenceCheckerInterface::class);
        \assert($checker instanceof UserExistenceCheckerInterface);

        return $checker;
    }
}
