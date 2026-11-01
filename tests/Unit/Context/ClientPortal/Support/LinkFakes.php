<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Support;

use App\Context\ClientPortal\Application\Port\LinkRequestBellInterface;
use App\Context\ClientPortal\Application\Port\OwnerAccount;
use App\Context\ClientPortal\Application\Port\OwnerAccountsInterface;
use App\Context\ClientPortal\Application\Port\PrescriberDirectoryInterface;
use App\Context\ClientPortal\Application\Port\SuggestionIdSignerInterface;

/**
 * The link suggestion ports on public fields: owner accounts by id, a
 * readable suggestion id (`s:<user>:<clinic>`), the clinic's animal
 * managers and the bells rung.
 */
final class LinkFakes implements OwnerAccountsInterface, SuggestionIdSignerInterface, PrescriberDirectoryInterface, LinkRequestBellInterface
{
    /** @var array<string, OwnerAccount> */
    public array $accounts = [];

    /** @var list<string> */
    public array $animalManagers = ['asv-1'];

    /** @var list<array{clinicId: string, userIds: list<string>, ownerUserId: string, key: string, title: string, detail: string}> */
    public array $bells = [];

    public function accountOf(string $userId): ?OwnerAccount
    {
        return $this->accounts[$userId] ?? null;
    }

    public function idOf(string $userId, string $clinicId): string
    {
        return 's:' . $userId . ':' . $clinicId;
    }

    public function veterinarians(string $clinicId): array
    {
        return [];
    }

    public function animalManagers(string $clinicId): array
    {
        return $this->animalManagers;
    }

    public function announce(string $clinicId, array $userIds, string $ownerUserId, string $key, string $title, string $detail): void
    {
        $this->bells[] = ['clinicId' => $clinicId, 'userIds' => $userIds, 'ownerUserId' => $ownerUserId, 'key' => $key, 'title' => $title, 'detail' => $detail];
    }
}
