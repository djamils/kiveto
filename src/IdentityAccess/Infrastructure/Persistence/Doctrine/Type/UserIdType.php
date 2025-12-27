<?php

declare(strict_types=1);

namespace App\IdentityAccess\Infrastructure\Persistence\Doctrine\Type;

use App\IdentityAccess\Domain\ValueObject\UserId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class UserIdType extends Type
{
    public const NAME = 'user_id';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getGuidTypeDeclarationSQL($column);
    }

    /** @param mixed $value */
    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof UserId) {
            return $value->toString();
        }

        if (\is_string($value)) {
            return $value;
        }

        throw new \InvalidArgumentException('Invalid UserId value.');
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?UserId
    {
        if (null === $value || $value instanceof UserId) {
            return $value;
        }

        if (\is_string($value)) {
            return UserId::fromString($value);
        }

        throw new \InvalidArgumentException('Invalid UserId database value.');
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
