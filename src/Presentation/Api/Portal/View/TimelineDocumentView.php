<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\View;

use ApiPlatform\Metadata\ApiProperty;

final readonly class TimelineDocumentView
{
    public function __construct(
        public string $id,
        #[ApiProperty(schema: ['type' => 'string', 'enum' => ['report', 'prescription', 'invoice', 'lab_result', 'certificate']])]
        public string $type,
    ) {
    }
}
