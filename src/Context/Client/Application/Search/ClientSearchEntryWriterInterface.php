<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Search;

interface ClientSearchEntryWriterInterface
{
    public function upsert(ClientSearchEntryData $data): void;

    public function updateName(
        string $clientId,
        string $clinicId,
        string $firstName,
        string $lastName,
    ): void;

    public function updateContactMethods(
        string $clientId,
        string $clinicId,
        ?string $phone,
        ?string $email,
    ): void;

    public function markArchived(string $clientId, string $clinicId): void;

    public function markActive(string $clientId, string $clinicId): void;
}
