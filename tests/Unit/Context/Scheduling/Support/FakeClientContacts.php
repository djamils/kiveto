<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Support;

use App\Context\Scheduling\Application\Port\ClientContact;
use App\Context\Scheduling\Application\Port\ClientContactInterface;

/**
 * Sophie Dubois, Milo's owner: e-mail only by default; Marc Lefèvre, another
 * client of the clinic, by SMS.
 */
final class FakeClientContacts implements ClientContactInterface
{
    /** @var array<string, ClientContact> */
    public array $contacts = [];

    public function __construct()
    {
        $this->contacts[Appointments::OWNER]   = self::sophie();
        $this->contacts[Appointments::LEFEVRE] = new ClientContact(Appointments::LEFEVRE, 'Marc Lefèvre', 'Monsieur Lefèvre', null, '+33698765432', ClientContact::SMS, [], false);
    }

    /**
     * @param list<string> $reminderChannels
     */
    public static function sophie(
        ?string $email = 's.dubois@email.fr',
        ?string $mobile = null,
        ?string $transactionalChannel = ClientContact::EMAIL,
        array $reminderChannels = [],
        bool $archived = false,
    ): ClientContact {
        return new ClientContact(Appointments::OWNER, 'Sophie Dubois', 'Madame Dubois', $email, $mobile, $transactionalChannel, $reminderChannels, $archived);
    }

    public function contactOf(string $clinicId, string $clientId): ?ClientContact
    {
        return $this->contacts[$clientId] ?? null;
    }
}
