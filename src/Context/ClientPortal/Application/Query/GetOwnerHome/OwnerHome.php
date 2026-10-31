<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\GetOwnerHome;

use App\Context\ClientPortal\Application\Query\ListOwnerPets\OwnerPet;
use App\Context\ClientPortal\Application\Query\ListPublishedDocuments\PortalDocument;

/** Alive pets only; an empty section is an empty list (no next appointment: null). */
final readonly class OwnerHome
{
    /**
     * @param list<OwnerPet>       $pets            alive visible pets
     * @param list<HomeCareItem>   $careItems       at most 3, nearest due date first
     * @param list<PortalDocument> $latestDocuments at most 2, newest first
     */
    public function __construct(
        public ?NextAppointment $nextAppointment,
        public array $pets,
        public array $careItems,
        public array $latestDocuments,
    ) {
    }
}
