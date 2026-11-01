<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\ResolvePortalRequest;

use App\Shared\Application\Bus\CommandInterface;

/**
 * VetApp « Traiter » (`done`) or « Refuser » (`rejected`) on a pet change
 * or renewal request, then the owner told on the client's transactional
 * channel (S6f). Answers the request's status afterwards: `done`,
 * `rejected`, or `closed` when its pet turned out archived or deceased
 * (closed on the spot, nothing sent).
 *
 * @throws \App\Context\ClientPortal\Domain\Exception\PortalRequestNotFoundException        unknown in the clinic, or a questionnaire
 * @throws \App\Context\ClientPortal\Domain\Exception\PortalRequestAlreadyResolvedException no longer submitted (`resolvedByName` for the flash)
 * @throws \App\Context\ClientPortal\Domain\Exception\InvalidPortalRequestException         `message` over 280, `resultingPrescriptionId` not an issued prescription of the pet issued after the request
 * @throws \InvalidArgumentException                                                        unknown outcome, or a prescription linked to a refusal or a change request
 */
final readonly class ResolvePortalRequest implements CommandInterface
{
    /**
     * @param string  $outcome                 done|rejected
     * @param string  $actorName               the staff member's name, shown to colleagues (« Demande déjà traitée par … »)
     * @param ?string $resultingPrescriptionId renewals processed only: the prescription written for it
     */
    public function __construct(
        public string $clinicId,
        public string $requestId,
        public string $outcome,
        public string $actorUserId,
        public string $actorName,
        public ?string $message = null,
        public ?string $resultingPrescriptionId = null,
    ) {
    }
}
