<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Client;

use App\System\IdentityAccess\Infrastructure\Security\Symfony\SecurityUser;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * The staff member signed in, as the consent history names them: their user
 * id and the name they sign with (StaffNameResolver).
 */
final readonly class StaffConsentAuthor
{
    public function __construct(
        private Security $security,
        private StaffNameResolver $staffNameResolver,
    ) {
    }

    /**
     * @return array{id: ?string, label: string}
     */
    public function of(string $clinicId): array
    {
        $user = $this->security->getUser();

        return [
            'id'    => $user instanceof SecurityUser ? $user->id() : null,
            'label' => $this->staffNameResolver->resolve($clinicId, $user),
        ];
    }
}
