<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Account;

use App\Presentation\Backoffice\Audit\ConsoleAuditTrail;
use App\Presentation\Backoffice\Security\CurrentAdmin;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use App\System\Audit\Domain\ValueObject\AuditCategory;
use App\System\IdentityAccess\Application\Command\UpdateBackofficeProfile\UpdateBackofficeProfile;
use App\System\IdentityAccess\Application\Query\GetBackofficeProfile\BackofficeProfileView;
use App\System\IdentityAccess\Application\Query\GetBackofficeProfile\GetBackofficeProfile;

/**
 * The profile and the preferences cards save the same profile, each its
 * own fields, the others kept as they are.
 */
final readonly class AccountProfileSaver
{
    public const string CSRF_ID = 'backoffice_account';

    public function __construct(
        private QueryBusInterface $queryBus,
        private CommandBusInterface $commandBus,
        private CurrentAdmin $currentAdmin,
        private ConsoleAuditTrail $auditTrail,
    ) {
    }

    /**
     * @param array{display_name?: string, time_zone?: string, notify_registry_failure?: bool, notify_invitation_expiry?: bool} $changes
     */
    public function save(array $changes, string $action, string $summary): void
    {
        $admin   = $this->currentAdmin->get();
        $profile = $this->queryBus->ask(new GetBackofficeProfile($admin->id));
        \assert($profile instanceof BackofficeProfileView);

        $this->commandBus->dispatch(new UpdateBackofficeProfile(
            userId: $admin->id,
            displayName: $changes['display_name'] ?? $profile->displayName,
            timeZone: $changes['time_zone'] ?? $profile->timeZone,
            notifyOnRegistryFailure: $changes['notify_registry_failure'] ?? $profile->notifyOnRegistryFailure,
            notifyOnInvitationExpiry: $changes['notify_invitation_expiry'] ?? $profile->notifyOnInvitationExpiry,
        ));

        $this->currentAdmin->forget();
        $this->auditTrail->record($action, AuditCategory::ACCESS, $summary, targetUserId: $admin->id);
    }
}
