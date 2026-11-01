<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

/** Opens a client file for a new client confirmed from « Demandes Portal » (Client context). */
interface ClientRegistrationInterface
{
    /**
     * A plain individual file without animals, the account phone as its
     * primary `mobile`; answers its id.
     *
     * @param string $mobilePhone E.164
     * @param string $actorUserId the staff member who confirmed
     */
    public function register(string $clinicId, string $firstName, string $lastName, string $mobilePhone, string $actorUserId): string;
}
