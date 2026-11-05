<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Support;

use App\Context\Scheduling\Application\Port\AnimalDirectoryInterface;
use App\Context\Scheduling\Application\Port\ClientContact;
use App\Context\Scheduling\Application\Port\ClientContactInterface;
use App\Context\Scheduling\Application\Service\AppointmentParties;

/**
 * The client and animal checks of the appointment handlers, for tests that
 * are about something else: every id is the clinic's, or none is.
 */
final class Parties
{
    public static function ofTheClinic(): AppointmentParties
    {
        return self::known(true);
    }

    public static function ofAnotherClinic(): AppointmentParties
    {
        return self::known(false);
    }

    private static function known(bool $known): AppointmentParties
    {
        return new AppointmentParties(
            new readonly class($known) implements ClientContactInterface {
                public function __construct(private bool $known)
                {
                }

                public function contactOf(string $clinicId, string $clientId): ?ClientContact
                {
                    return $this->known ? new ClientContact($clientId, 'Sophie Dubois', 'Madame Dubois', null, null, null, [], false) : null;
                }
            },
            new readonly class($known) implements AnimalDirectoryInterface {
                public function __construct(private bool $known)
                {
                }

                public function nameOf(string $clinicId, string $animalId): ?string
                {
                    return $this->known ? 'Milo' : null;
                }
            },
        );
    }
}
