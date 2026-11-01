<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Command\ConfirmNewClientAppointment;

use App\Shared\Application\Bus\CommandInterface;

/**
 * The clinic confirms a new client's pending request (slice 7, « Demandes
 * Portal » « Nouveau client »): the client file and the animal chosen or
 * created by staff are set on it, then it is confirmed.
 */
final readonly class ConfirmNewClientAppointment implements CommandInterface
{
    public function __construct(
        public string $appointmentId,
        public string $clinicId,
        public string $ownerId,
        public string $animalId,
    ) {
    }
}
