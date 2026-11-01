<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Service;

use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\ValueObject\PhoneNumber;
use App\System\IdentityAccess\Domain\Repository\UserRepositoryInterface;
use App\System\IdentityAccess\Domain\User;
use App\System\IdentityAccess\Domain\ValueObject\UserId;
use App\System\IdentityAccess\Domain\ValueObject\UserType;

/**
 * Creates the owner account of a proven phone, the caller holding the
 * per-phone mutex and having found no account for it. The account is
 * inserted with SQL: an owner e-mail taken meanwhile by another phone only
 * costs the new account its e-mail.
 */
final readonly class PortalAccountRegistrar
{
    /** Width of `identity_access__users.email`; client files allow longer ones. */
    private const int EMAIL_MAX_LENGTH = 180;

    public function __construct(
        private UserRepositoryInterface $users,
        private UuidGeneratorInterface $uuids,
        private DomainEventPublisher $domainEventPublisher,
    ) {
    }

    /**
     * @param ?string $email copied unless another owner account already holds
     *                       it (families) or it does not fit the column;
     *                       never verified
     *
     * @return string the new account's id
     */
    public function register(PhoneNumber $phone, ?string $email, \DateTimeImmutable $now): string
    {
        $user = User::registerPortal(UserId::fromString($this->uuids->generate()), $phone, $this->freeEmail($email), $now);

        if (!$this->users->addPortalIfPhoneFree($user)) {
            // The phone is locked by the caller: only the e-mail key can have
            // refused it — another phone took the address meanwhile.
            $user = User::registerPortal(UserId::fromString($this->uuids->generate()), $phone, null, $now);

            if (!$this->users->addPortalIfPhoneFree($user)) {
                throw new \LogicException(\sprintf('The owner account of "%s" could not be created.', $phone->toString()));
            }
        }

        $this->domainEventPublisher->publish($user);

        return $user->id()->toString();
    }

    private function freeEmail(?string $email): ?string
    {
        $email = null === $email ? '' : mb_strtolower(mb_trim($email));

        if ('' === $email || mb_strlen($email) > self::EMAIL_MAX_LENGTH || null !== $this->users->findByEmailAndType($email, UserType::PORTAL)) {
            return null;
        }

        return $email;
    }
}
