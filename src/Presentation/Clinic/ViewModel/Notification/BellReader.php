<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Notification;

use App\Shared\Application\Context\CurrentClinicContextInterface;
use App\System\IdentityAccess\Infrastructure\Security\Symfony\SecurityUser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Whose bell the bell endpoints serve: the signed-in member, in the current
 * clinic (a member of two clinics only sees the current one's entries).
 */
final readonly class BellReader
{
    public function __construct(
        private Security $security,
        private CurrentClinicContextInterface $currentClinicContext,
    ) {
    }

    /**
     * @return array{0: string, 1: string} user id, clinic id
     */
    public function owner(): array
    {
        $user     = $this->security->getUser();
        $clinicId = $this->currentClinicContext->getCurrentClinicId();

        if (!$user instanceof SecurityUser || null === $clinicId) {
            throw new NotFoundHttpException('No current clinic.');
        }

        return [$user->id(), $clinicId->toString()];
    }
}
