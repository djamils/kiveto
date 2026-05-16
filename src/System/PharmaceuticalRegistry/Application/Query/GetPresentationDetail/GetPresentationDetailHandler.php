<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Application\Query\GetPresentationDetail;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetPresentationDetailHandler
{
    public function __invoke(GetPresentationDetail $query): null
    {
        return null;
    }
}
