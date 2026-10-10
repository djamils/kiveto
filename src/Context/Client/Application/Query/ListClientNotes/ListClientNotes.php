<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Query\ListClientNotes;

use App\Shared\Application\Bus\QueryInterface;

final readonly class ListClientNotes implements QueryInterface
{
    public function __construct(
        public string $clinicId,
        public string $clientId,
    ) {
    }
}
