<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\ConfirmBookingRequest;

use App\Shared\Application\Bus\CommandInterface;

/**
 * Staff confirm a « Nouveau client » request from « Demandes Portal »: an
 * existing client file matching the account's phone, or a new one; an
 * animal of that client, or the new pet registered.
 */
final readonly class ConfirmBookingRequest implements CommandInterface
{
    public const string EXISTING = 'existing';
    public const string NEW      = 'new';

    /**
     * @param string  $clientChoice existing|new
     * @param ?string $clientId     the matching file, when existing
     * @param string  $animalChoice existing|new
     * @param ?string $animalId     the client's animal, when existing
     * @param string  $actorName    the staff member's name, kept on the request
     */
    public function __construct(
        public string $clinicId,
        public string $requestId,
        public string $clientChoice,
        public ?string $clientId,
        public string $animalChoice,
        public ?string $animalId,
        public string $actorUserId,
        public string $actorName,
    ) {
    }
}
