<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\View;

/** The answer of `POST …/change-requests`: the request sent to the clinic. */
final readonly class PortalRequestCreatedView
{
    public function __construct(public string $requestId)
    {
    }
}
