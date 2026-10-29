<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Application\Query\GetClientNotificationContact;

use App\Context\Client\Application\Port\ClientNotificationContactReaderInterface;
use App\Context\Client\Application\Query\GetClientNotificationContact\ClientNotificationContact;
use App\Context\Client\Domain\ValueObject\ClinicId;

/** Knows a few clients and records each read. */
final class RecordingContactReader implements ClientNotificationContactReaderInterface
{
    /** @var list<array{string, list<string>}> */
    public array $calls = [];

    /**
     * @param list<string> $known
     */
    public function __construct(private readonly array $known)
    {
    }

    public function findForClients(ClinicId $clinicId, array $clientIds): array
    {
        $this->calls[] = [$clinicId->toString(), $clientIds];
        $contacts      = [];

        foreach (array_intersect($clientIds, $this->known) as $id) {
            $contacts[$id] = new ClientNotificationContact($id, 'Hélène Lambert', 'Mme', 'Hélène', 'Lambert', null, null, null, [], false);
        }

        return $contacts;
    }
}
