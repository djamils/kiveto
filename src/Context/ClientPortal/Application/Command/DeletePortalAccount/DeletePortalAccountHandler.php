<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\DeletePortalAccount;

use App\Context\ClientPortal\Application\Command\DeleteUserDataExports\DeleteUserDataExports;
use App\Context\ClientPortal\Application\Port\PortalAccountErasureInterface;
use App\Context\ClientPortal\Application\Port\PortalRecipientMaskerInterface;
use App\Context\ClientPortal\Domain\Invitation\PortalInvitationRepositoryInterface;
use App\Context\ClientPortal\Domain\Link\ClientLinkRepositoryInterface;
use App\Context\ClientPortal\Domain\Profile\OwnerProfileRepositoryInterface;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\PhoneNumber;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * The order matters: the phone lock first (IdentityAccess purges the
 * phone's traces under it), the account anonymised last (it deletes the
 * lock row and frees the phone). Every step finds nothing left to do when
 * it already ran, so a retry after any failure completes the erasure.
 */
#[AsMessageHandler]
final readonly class DeletePortalAccountHandler
{
    /**
     * @param iterable<AccountErasureStepInterface> $steps
     */
    public function __construct(
        private PortalAccountErasureInterface $accounts,
        private ClientLinkRepositoryInterface $links,
        private PortalInvitationRepositoryInterface $invitations,
        private OwnerProfileRepositoryInterface $profiles,
        private PortalRecipientMaskerInterface $messages,
        private CommandBusInterface $commandBus,
        private DomainEventPublisher $eventPublisher,
        private ClockInterface $clock,
        #[AutowireIterator(AccountErasureStepInterface::class)]
        private iterable $steps,
    ) {
    }

    public function __invoke(DeletePortalAccount $command): void
    {
        $owner = $this->accounts->purgeTraces($command->userId);
        $now   = $this->clock->now();

        // Access withdrawn from every clinic; the clinics' consents are not changed (D5).
        foreach ($this->links->findByUser($command->userId) as $link) {
            $link->revoke($now);
            $this->links->save($link);
            $this->eventPublisher->publish($link);
        }

        if (null !== $owner->phone) {
            foreach ($this->invitations->findPendingByPhoneForUpdate(PhoneNumber::fromString($owner->phone)) as $invitation) {
                $invitation->revoke($now);
                $this->invitations->save($invitation);
            }
        }

        foreach ($this->steps as $step) {
            $step->erase($command->userId, $owner);
        }

        $this->commandBus->dispatch(new DeleteUserDataExports($command->userId));

        if (null !== $owner->phone) {
            $this->messages->mask($command->userId, $owner->phone, $owner->email);
        }

        $profile = $this->profiles->findByUserId($command->userId);
        if (null !== $profile) {
            $profile->anonymise();
            $this->profiles->updateIdentity($profile);
        }

        $this->accounts->anonymise($command->userId);
    }
}
