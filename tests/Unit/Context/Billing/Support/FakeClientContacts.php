<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Billing\Support;

use App\Context\Billing\Application\Port\ClientContact;
use App\Context\Billing\Application\Port\ClientContactInterface;

final class FakeClientContacts implements ClientContactInterface
{
    /** @var array<string, ClientContact> */
    public array $contacts = [];

    /** @var list<list<string>> the client ids of each batch read */
    public array $batches = [];

    public function __construct(ClientContact ...$contacts)
    {
        foreach ($contacts as $contact) {
            $this->contacts[$contact->clientId] = $contact;
        }
    }

    /**
     * @param list<string> $billing
     */
    public static function contact(
        string $clientId = Invoices::CLIENT,
        ?string $email = 's.dubois@email.fr',
        ?string $mobile = '+33612345678',
        array $billing = [],
        bool $archived = false,
    ): ClientContact {
        $transactional = null !== $email ? ClientContact::EMAIL : (null !== $mobile ? ClientContact::SMS : null);

        return new ClientContact($clientId, 'Sophie Dubois', 'Madame Dubois', $email, $mobile, $billing, $transactional, $archived);
    }

    public function contactOf(string $clinicId, string $clientId): ?ClientContact
    {
        return $this->contacts[$clientId] ?? null;
    }

    public function contactsOf(string $clinicId, array $clientIds): array
    {
        $this->batches[] = $clientIds;

        return array_intersect_key($this->contacts, array_flip($clientIds));
    }
}
