<?php

declare(strict_types=1);

namespace App\Fixtures\System\IdentityAccess\Factory;

use App\System\IdentityAccess\Domain\ValueObject\UserStatus;
use App\System\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\PortalUserEntity;
use Symfony\Component\Uid\Uuid;

/**
 * Owner accounts sign in with their mobile phone and a one-time code: a
 * verified phone, no password, no e-mail unless one is given.
 *
 * @extends AbstractUserFactory<PortalUserEntity>
 */
final class PortalUserFactory extends AbstractUserFactory
{
    public static function class(): string
    {
        return PortalUserEntity::class;
    }

    public function withPhone(string $phone): static
    {
        return $this->with(['phone' => $phone]);
    }

    protected function defaults(): array|callable
    {
        $createdAt = \DateTimeImmutable::createFromMutable(self::faker()->dateTime());

        return [
            'id'              => Uuid::v7(),
            'status'          => UserStatus::ACTIVE,
            'createdAt'       => $createdAt,
            'email'           => null,
            'phone'           => self::faker()->unique()->numerify('+3367#######'),
            'phoneVerifiedAt' => $createdAt,
            'passwordHash'    => '!' . bin2hex(random_bytes(16)),
        ];
    }
}
