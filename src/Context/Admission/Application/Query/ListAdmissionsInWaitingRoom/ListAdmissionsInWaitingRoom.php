<?php

declare(strict_types=1);

namespace App\Context\Admission\Application\Query\ListAdmissionsInWaitingRoom;

use App\Shared\Application\Bus\QueryInterface;

final readonly class ListAdmissionsInWaitingRoom implements QueryInterface
{
    public function __construct(public string $clinicId)
    {
    }
}
