<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Team;

use App\Context\Clinic\Application\Query\Clinic\GetClinicSettings\ClinicSettingsView;
use App\Context\Clinic\Application\Query\Clinic\GetClinicSettings\GetClinicSettings;
use App\Context\Clinic\Application\Query\Staff\GetStaffPrintedName\GetStaffPrintedName;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use App\System\IdentityAccess\Application\Command\SendInvitation\SendInvitation;
use App\System\IdentityAccess\Infrastructure\Security\Symfony\SecurityUser;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Sends (or sends again) the invitation e-mail of a member: the inviter, the
 * clinic and the role are resolved here through Clinic queries, so that
 * IdentityAccess never reads a clinic.
 */
final readonly class TeamInvitationSender
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private QueryBusInterface $queryBus,
        private Security $security,
    ) {
    }

    /** Whether an e-mail left (false: the account is no longer waiting for an invitation). */
    public function send(string $clinicId, string $userId, string $firstName, ClinicMemberRole $role): bool
    {
        $settings = $this->queryBus->ask(new GetClinicSettings($clinicId));
        \assert($settings instanceof ClinicSettingsView);

        $viewer  = $this->security->getUser();
        $inviter = $viewer instanceof SecurityUser ? $this->queryBus->ask(new GetStaffPrintedName($clinicId, $viewer->id())) : null;

        $tokenId = $this->commandBus->dispatch(new SendInvitation(
            userId: $userId,
            clinicId: $clinicId,
            inviterPrintedName: \is_string($inviter) && '' !== $inviter ? $inviter : $settings->name,
            clinicName: $settings->name,
            clinicCity: $settings->letterhead['city'] ?? '',
            inviteeFirstName: $firstName,
            roleLabel: TeamPageBuilder::ROLES[$role->value]['l'],
        ));

        return null !== $tokenId;
    }
}
