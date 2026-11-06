<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Security;

use App\Shared\Application\Bus\QueryBusInterface;
use App\System\IdentityAccess\Application\Query\GetBackofficeProfile\BackofficeProfileView;
use App\System\IdentityAccess\Application\Query\GetBackofficeProfile\GetBackofficeProfile;
use App\System\IdentityAccess\Domain\ValueObject\UserType;
use App\System\IdentityAccess\Infrastructure\Security\Symfony\SecurityUser;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * The admin of the current console request, with the name and the display
 * time zone of his profile (read once per request).
 */
final class CurrentAdmin
{
    private const string DEFAULT_TIME_ZONE = 'Europe/Paris';

    /** @var array<string, ConsoleAdmin> */
    private array $resolved = [];

    public function __construct(
        private readonly Security $security,
        private readonly QueryBusInterface $queryBus,
    ) {
    }

    public function find(): ?ConsoleAdmin
    {
        $user = $this->security->getUser();

        if (!$user instanceof SecurityUser || UserType::BACKOFFICE !== $user->type()) {
            return null;
        }

        if (isset($this->resolved[$user->id()])) {
            return $this->resolved[$user->id()];
        }

        $profile = $this->queryBus->ask(new GetBackofficeProfile($user->id()));
        $email   = $user->getUserIdentifier();

        return $this->resolved[$user->id()] = new ConsoleAdmin(
            id: $user->id(),
            email: $email,
            name: $profile instanceof BackofficeProfileView ? $profile->displayName : BackofficeProfileView::nameFromEmail($email),
            timeZone: new \DateTimeZone($profile instanceof BackofficeProfileView ? $profile->timeZone : self::DEFAULT_TIME_ZONE),
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

    /**
     * After the admin renamed himself or changed his zone.
     */
    public function forget(): void
    {
        $this->resolved = [];
    }
}
