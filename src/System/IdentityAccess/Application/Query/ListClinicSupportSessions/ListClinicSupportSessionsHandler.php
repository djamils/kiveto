<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Query\ListClinicSupportSessions;

use App\System\IdentityAccess\Application\Query\SupportSessionView;
use App\System\IdentityAccess\Domain\Support\SupportSessionRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListClinicSupportSessionsHandler
{
    public function __construct(
        private SupportSessionRepositoryInterface $sessions,
    ) {
    }

    /**
     * @return list<SupportSessionView>
     */
    public function __invoke(ListClinicSupportSessions $query): array
    {
        return array_map(SupportSessionView::of(...), $this->sessions->ofClinic($query->clinicId, max(1, $query->limit)));
    }
}
