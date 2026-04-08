<?php

declare(strict_types=1);

namespace App\System\AccessControl\Application\Query\ListClinicsForUser;

use App\System\AccessControl\Application\Port\ClinicMembershipReadRepositoryInterface;
use App\System\AccessControl\Domain\ValueObject\UserId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListClinicsForUserHandler
{
    public function __construct(
        private ClinicMembershipReadRepositoryInterface $readRepository,
    ) {
    }

    /**
     * @return list<AccessibleClinic>
     */
    public function __invoke(ListClinicsForUser $query): array
    {
        $userId = UserId::fromString($query->userId);

        return $this->readRepository->findAccessibleClinicsForUser($userId);
    }
}
