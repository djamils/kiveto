<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Client;

use App\Context\Clinic\Application\Query\Staff\ListClinicVeterinarians\ClinicVeterinarianItem;
use App\Context\Clinic\Application\Query\Staff\ListClinicVeterinarians\ListClinicVeterinarians;
use App\Shared\Application\Bus\QueryBusInterface;
use App\System\IdentityAccess\Infrastructure\Security\Symfony\SecurityUser;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * The name a staff member signs notes and journal entries with.
 *
 * Only practitioners expose a display name through the Clinic context today;
 * anyone else signs with their login until that context offers a query for
 * the whole team.
 */
final readonly class StaffNameResolver
{
    public function __construct(
        private QueryBusInterface $queryBus,
    ) {
    }

    public function resolve(string $clinicId, ?UserInterface $user): string
    {
        if (!$user instanceof SecurityUser) {
            return 'Équipe de la clinique';
        }

        $members = $this->queryBus->ask(new ListClinicVeterinarians($clinicId));
        \assert(\is_array($members));

        foreach ($members as $member) {
            \assert($member instanceof ClinicVeterinarianItem);

            if ($member->userId === $user->id() && null !== $member->displayName) {
                return $member->displayName;
            }
        }

        return $user->getUserIdentifier();
    }
}
