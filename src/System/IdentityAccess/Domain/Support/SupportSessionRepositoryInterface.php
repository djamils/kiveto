<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Domain\Support;

interface SupportSessionRepositoryInterface
{
    public function save(SupportSession $session): void;

    public function find(string $id): ?SupportSession;

    public function findByHandoffHashForUpdate(string $handoffHash): ?SupportSession;

    /**
     * The latest sessions of a clinic, newest first.
     *
     * @return list<SupportSession>
     */
    public function ofClinic(string $clinicId, int $limit): array;
}
