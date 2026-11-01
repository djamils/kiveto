<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Security;

use App\Context\Clinic\Application\Query\Staff\ListPermissionsForUserInClinic\ListPermissionsForUserInClinic;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use App\System\IdentityAccess\Infrastructure\Security\Symfony\SecurityUser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Settings are not for everyone: what a member may do in a clinic comes from
 * the role they hold there, which the Clinic context alone can answer.
 */
final readonly class ClinicPermissionGuard
{
    public const string MANAGE_STAFF   = 'manage_staff';
    public const string MANAGE_BILLING = 'manage_billing';
    public const string MANAGE_CLIENTS = 'manage_clients';

    // Confirm or decline an online booking request (ClinicPermission::MANAGE_APPOINTMENTS).
    public const string MANAGE_APPOINTMENTS = 'manage_appointments';

    // See the « Demandes Portal » queue and answer pet change requests (ClinicPermission::MANAGE_ANIMALS).
    public const string MANAGE_ANIMALS = 'manage_animals';

    // Answer a prescription renewal request (ClinicPermission::CREATE_PRESCRIPTION).
    public const string CREATE_PRESCRIPTION = 'create_prescription';

    public function __construct(
        private QueryBusInterface $queryBus,
        private CurrentClinicContextInterface $currentClinicContext,
        private Security $security,
    ) {
    }

    /**
     * @throws AccessDeniedException when the member does not hold the permission
     */
    public function require(string $permission): void
    {
        if (!$this->has($permission)) {
            throw new AccessDeniedException(\sprintf('Permission "%s" is required.', $permission));
        }
    }

    public function has(string $permission): bool
    {
        return \in_array($permission, $this->permissions(), true);
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        $user = $this->security->getUser();

        // Without a signed-in member there is no clinic session to read either.
        if (!$user instanceof SecurityUser) {
            return [];
        }

        $clinicId = $this->currentClinicContext->getCurrentClinicId();

        if (null === $clinicId) {
            return [];
        }

        $permissions = $this->queryBus->ask(new ListPermissionsForUserInClinic(
            userId: $user->id(),
            clinicId: $clinicId->toString(),
        ));

        return \is_array($permissions) ? array_values(array_filter($permissions, \is_string(...))) : [];
    }
}
