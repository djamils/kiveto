<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\ConfirmBookingRequest;

use App\Shared\Application\Bus\CommandInterface;

/**
 * The one confirmation of a « Nouveau client » request (S7-01): staff
 * choose in « Demandes Portal » an existing client file matching the
 * account's phone, or a new one, and an animal of that client, or the new
 * pet registered. Scheduling confirms it the same way from the agenda, a
 * check-in, a consultation start or a completion (`forAppointment()`), with
 * the default choices.
 */
final readonly class ConfirmBookingRequest implements CommandInterface
{
    public const string EXISTING = 'existing';
    public const string NEW      = 'new';
    /**
     * No staff choice: the client file a sibling request was confirmed for,
     * else the one non-archived file of the clinic whose mobile is the
     * account's phone, else a new file; the appointment's animal when it is
     * that client's, else the new pet.
     */
    public const string DEFAULT = 'default';

    /**
     * @param ?string $requestId     the request, or null when found by $appointmentId
     * @param string  $clientChoice  existing|new|default
     * @param ?string $clientId      the matching file, when existing
     * @param string  $animalChoice  existing|new|default
     * @param ?string $animalId      the client's animal, when existing (the appointment's, when default)
     * @param ?string $actorName     the staff member's name, kept on the request (null from Scheduling)
     * @param ?string $appointmentId the appointment held by the request, when $requestId is null
     * @param bool    $silent        the client is on site: confirmed without message, whatever its expiry
     */
    public function __construct(
        public string $clinicId,
        public ?string $requestId,
        public string $clientChoice,
        public ?string $clientId,
        public string $animalChoice,
        public ?string $animalId,
        public ?string $actorUserId,
        public ?string $actorName,
        public ?string $appointmentId = null,
        public bool $silent = false,
    ) {
    }

    /** Scheduling confirms a new client's appointment (agenda, check-in, consultation, completion). */
    public static function forAppointment(string $clinicId, string $appointmentId, ?string $animalId, bool $silent): self
    {
        return new self($clinicId, null, self::DEFAULT, null, self::DEFAULT, $animalId, null, null, $appointmentId, $silent);
    }
}
