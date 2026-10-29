<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Port;

/**
 * How the owner is reached, read from the Client context.
 */
interface ClientContactInterface
{
    public function contactOf(string $clinicId, string $clientId): ?ClientContact;
}
