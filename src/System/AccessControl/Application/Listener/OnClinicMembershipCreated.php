<?php

declare(strict_types=1);

namespace App\System\AccessControl\Application\Listener;

use App\Context\Clinic\Domain\Staff\Event\ClinicMembershipCreated;
use App\System\AccessControl\Domain\Repository\RoleAssignmentRepositoryInterface;
use App\System\AccessControl\Domain\RoleAssignment;
use App\System\AccessControl\Domain\ValueObject\SubjectId;
use App\System\AccessControl\Domain\ValueObject\TenantId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'messenger.bus.event')]
final readonly class OnClinicMembershipCreated
{
    public function __construct(
        private RoleAssignmentRepositoryInterface $roleAssignmentRepository,
    ) {
    }

    public function __invoke(ClinicMembershipCreated $event): void
    {
        $payload = $event->payload();
        \assert(\is_string($payload['userId']));
        \assert(\is_string($payload['clinicId']));
        \assert(\is_string($payload['role']));

        $subjectId = SubjectId::fromString($payload['userId']);
        $tenantId  = TenantId::fromString($payload['clinicId']);
        $roleKey   = $payload['role'];

        $assignment = RoleAssignment::create($subjectId, $tenantId, $roleKey);

        $this->roleAssignmentRepository->save($assignment);
    }
}
