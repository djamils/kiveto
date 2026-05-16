<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Application\Query\GetPresentationDetail;

use App\Shared\Application\Bus\QueryInterface;

final readonly class GetPresentationDetail implements QueryInterface
{
    public function __construct(
        public string $presentationId,
    ) {
    }
}
