<?php

declare(strict_types=1);

namespace App\Context\Consultation\Infrastructure\Adapter\Client;

use App\Context\Client\Application\Query\GetClientById\ClientView;
use App\Context\Client\Application\Query\GetClientById\GetClientById;
use App\Context\Consultation\Application\Port\ReportOwner;
use App\Shared\Application\Bus\QueryBusInterface;

/**
 * The owner of an animal (GetClientById), worded for the consultation report.
 * Used by the patient snapshot provider.
 */
final readonly class QueryBusOwnerReportReader
{
    public function __construct(private QueryBusInterface $queryBus)
    {
    }

    public function read(string $clinicId, string $clientId): ?ReportOwner
    {
        try {
            $client = $this->queryBus->ask(new GetClientById($clinicId, $clientId));
        } catch (\Throwable) {
            return null;
        }

        if (!$client instanceof ClientView) {
            return null;
        }

        $address = $client->postalAddress;

        return new ReportOwner(
            clientId: $client->id,
            fullName: $client->fullName(),
            phone: self::contactValue($client, 'phone'),
            email: self::contactValue($client, 'email'),
            address: null !== $address
                ? trim(\sprintf('%s %s %s', $address->streetLine1, $address->postalCode ?? '', $address->city))
                : null,
        );
    }

    /**
     * The primary contact of that type, else the first one.
     */
    private static function contactValue(ClientView $client, string $type): ?string
    {
        $fallback = null;

        foreach ($client->contactMethods as $method) {
            if ($method->type !== $type) {
                continue;
            }

            if ($method->isPrimary) {
                return $method->value;
            }

            $fallback ??= $method->value;
        }

        return $fallback;
    }
}
