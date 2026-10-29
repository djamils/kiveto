<?php

declare(strict_types=1);

namespace App\Tests\Integration\System\IdentityAccess\Infrastructure\Security;

use App\Fixtures\System\IdentityAccess\Factory\ClinicUserFactory;
use App\System\IdentityAccess\Domain\Repository\UserRepositoryInterface;
use App\System\IdentityAccess\Domain\ValueObject\UserId;
use App\System\IdentityAccess\Domain\ValueObject\UserType;
use App\System\IdentityAccess\Infrastructure\Security\Symfony\ContextUserProvider;
use App\System\IdentityAccess\Infrastructure\Security\Symfony\SecurityUser;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

/**
 * AC 6: a clinic session stored before the owner-account migration (its
 * user identified by e-mail) still reloads afterwards.
 */
final class ContextUserProviderRefreshTest extends KernelTestCase
{
    use Factories;

    public function testASessionStoredBeforeTheMigrationStillReloads(): void
    {
        $entity = ClinicUserFactory::new()->withEmail('claire.session@example.com')->create();
        $id     = $entity->getId()->toRfc4122();

        $users = self::getContainer()->get(UserRepositoryInterface::class);
        \assert($users instanceof UserRepositoryInterface);
        $fingerprint = $users->findById(UserId::fromString($id))?->passwordFingerprint() ?? '';

        $stored = unserialize(serialize(new SecurityUser($id, 'claire.session@example.com', UserType::CLINIC, ['ROLE_USER'], $fingerprint)));
        \assert($stored instanceof SecurityUser);

        $reloaded = new ContextUserProvider($users, UserType::CLINIC)->refreshUser($stored);

        self::assertInstanceOf(SecurityUser::class, $reloaded);
        self::assertSame([$id, 'claire.session@example.com'], [$reloaded->id(), $reloaded->getUserIdentifier()]);
        self::assertTrue($reloaded->isEqualTo($stored));
    }
}
