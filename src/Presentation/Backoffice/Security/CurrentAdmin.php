<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Security;

use App\System\IdentityAccess\Domain\ValueObject\UserType;
use App\System\IdentityAccess\Infrastructure\Security\Symfony\SecurityUser;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * The admin of the current console request.
 */
final readonly class CurrentAdmin
{
    private const string DEFAULT_TIME_ZONE = 'Europe/Paris';

    public function __construct(
        private Security $security,
    ) {
    }

    public function find(): ?ConsoleAdmin
    {
        $user = $this->security->getUser();

        if (!$user instanceof SecurityUser || UserType::BACKOFFICE !== $user->type()) {
            return null;
        }

        $email = $user->getUserIdentifier();

        return new ConsoleAdmin(
            id: $user->id(),
            email: $email,
            name: $email,
            timeZone: new \DateTimeZone(self::DEFAULT_TIME_ZONE),
        );
    }

    public function get(): ConsoleAdmin
    {
        return $this->find() ?? throw new \LogicException('No back-office admin is signed in.');
    }

    /**
     * The display time zone, Paris outside a console request.
     */
    public function timeZone(): \DateTimeZone
    {
        return $this->find()->timeZone ?? new \DateTimeZone(self::DEFAULT_TIME_ZONE);
    }
}
