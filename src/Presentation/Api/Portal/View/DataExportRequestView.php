<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\View;

/** The answer of `POST /me/data-export`: the export is being built. */
final readonly class DataExportRequestView
{
    public function __construct(
        public string $id,
        public string $status = 'pending',
    ) {
    }
}
