<?php

declare(strict_types=1);

namespace App\System\AccessControl\Application\Listener;

use App\Context\Clinic\Domain\Staff\Event\ClinicMembershipDisabled;
use App\System\AccessControl\Domain\Repository\RoleAssignmentRepositoryInterface;
use App\System\AccessControl\Domain\ValueObject\SubjectId;
use App\System\AccessControl\Domain\ValueObject\TenantId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'messenger.bus.event')]
final readonly class OnClinicMembershipDisabled
{
    public function __construct(
        private RoleAssignmentRepositoryInterface $roleAssignmentRepository,
    ) {
    }

    public function __invoke(ClinicMembershipDisabled $event): void
    {
        $payload = $event->payload();

        $subjectId = SubjectId::fromString($payload['userId']);
        $tenantId  = TenantId::fromString($payload['clinicId']);

        $this->roleAssignmentRepository->deleteBySubjectAndTenant($subjectId, $tenantId);
    }
}
