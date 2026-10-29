<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\SendInvitation;

use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\System\IdentityAccess\Application\Port\AccountLinkBuilderInterface;
use App\System\IdentityAccess\Application\Port\NotificationRequesterInterface;
use App\System\IdentityAccess\Application\Port\StaffInvitationRequest;
use App\System\IdentityAccess\Domain\Repository\UserRepositoryInterface;
use App\System\IdentityAccess\Domain\Token\AccountToken;
use App\System\IdentityAccess\Domain\Token\AccountTokenId;
use App\System\IdentityAccess\Domain\Token\AccountTokenRepositoryInterface;
use App\System\IdentityAccess\Domain\Token\TokenPurpose;
use App\System\IdentityAccess\Domain\ValueObject\UserId;
use App\System\IdentityAccess\Domain\ValueObject\UserStatus;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SendInvitationHandler
{
    public function __construct(
        private UserRepositoryInterface $users,
        private AccountTokenRepositoryInterface $tokens,
        private UuidGeneratorInterface $uuids,
        private ClockInterface $clock,
        private AccountLinkBuilderInterface $links,
        private NotificationRequesterInterface $notifications,
    ) {
    }

    public function __invoke(SendInvitation $command): ?string
    {
        // Locked: a double click issues one live link, not two.
        $user = $this->users->findByIdForUpdate(UserId::fromString($command->userId))
            ?? throw new \DomainException(\sprintf('Unknown user "%s".', $command->userId));

        if (UserStatus::PENDING !== $user->status()) {
            return null;
        }

        $now = $this->clock->now();
        $this->tokens->invalidateInvitations($command->userId, $command->clinicId, $now);

        $secret = AccountToken::newSecret();
        $token  = AccountToken::issue(AccountTokenId::fromString($this->uuids->generate()), $command->userId, TokenPurpose::INVITATION, $command->clinicId, $secret, $now);
        $this->tokens->save($token);

        $this->notifications->requestStaffInvitation(new StaffInvitationRequest(
            tokenId: $token->id()->toString(),
            userId: $command->userId,
            email: $user->email(),
            clinicId: $command->clinicId,
            firstName: $command->inviteeFirstName,
            inviterName: $command->inviterPrintedName,
            clinicName: $command->clinicName,
            clinicCity: $command->clinicCity,
            roleLabel: $command->roleLabel,
            activationUrl: $this->links->invitationUrl($secret),
        ));

        return $token->id()->toString();
    }
}
