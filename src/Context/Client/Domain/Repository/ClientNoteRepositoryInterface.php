<?php

declare(strict_types=1);

namespace App\Context\Client\Domain\Repository;

use App\Context\Client\Domain\ClientNote;

interface ClientNoteRepositoryInterface
{
    public function save(ClientNote $clientNote): void;
}
