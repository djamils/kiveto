<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\ListOwnerAppointments;

use App\Context\ClientPortal\Application\Port\AppointmentScope;
use App\Shared\Application\Bus\QueryInterface;

/**
 * One page of the appointments of the client files the account reaches in
 * the clinic and of the pets it sees there. Answers a
 * CursorPage<PortalAppointment>; a cursor of another shape throws
 * InvalidCursorException.
 */
final readonly class ListOwnerAppointments implements QueryInterface
{
    public function __construct(
        public string $userId,
        public string $clinicId,
        public AppointmentScope $scope,
        public ?string $cursor = null,
        public ?int $limit = null,
    ) {
    }
}
