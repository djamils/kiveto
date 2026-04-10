<?php

declare(strict_types=1);

namespace App\System\AccessControl\Application\Listener;

use App\Context\Clinic\Domain\Staff\Event\ClinicMembershipRoleChanged;
use App\System\AccessControl\Domain\Repository\RoleAssignmentRepositoryInterface;
use App\System\AccessControl\Domain\ValueObject\SubjectId;
use App\System\AccessControl\Domain\ValueObject\TenantId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'messenger.bus.event')]
final readonly class OnClinicMembershipRoleChanged
{
    public function __construct(
        private RoleAssignmentRepositoryInterface $roleAssignmentRepository,
    ) {
    }

    public function __invoke(ClinicMembershipRoleChanged $event): void
    {
        $payload = $event->payload();

        $subjectId = SubjectId::fromString($payload['userId']);
        $tenantId  = TenantId::fromString($payload['clinicId']);
        $newRole   = $payload['newRole'];

        $assignment = $this->roleAssignmentRepository->findBySubjectAndTenant($subjectId, $tenantId);

        if (null === $assignment) {
            return;
        }

        $assignment->changeRoleKey($newRole);

        $this->roleAssignmentRepository->save($assignment);
    }
}
