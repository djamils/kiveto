<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Query\ListClinicGroups;

use App\Context\Clinic\Domain\ValueObject\ClinicGroupStatus;
use App\Shared\Application\Bus\QueryInterface;

final readonly class ListClinicGroups implements QueryInterface
{
    public function __construct(
        public ?ClinicGroupStatus $status = null,
    ) {
    }
}
