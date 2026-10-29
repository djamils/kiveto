<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\View;

use ApiPlatform\Metadata\ApiProperty;

final readonly class InvitationLookupView
{
    /**
     * @param list<string> $petNames active animals of the client file
     */
    public function __construct(
        public string $invitationId,
        public InvitationClinicView $clinic,
        public ?string $email,
        public string $maskedPhone,
        public array $petNames,
        #[ApiProperty(schema: ['type' => 'string', 'format' => 'date-time'])]
        public string $expiresAt,
    ) {
    }
}
