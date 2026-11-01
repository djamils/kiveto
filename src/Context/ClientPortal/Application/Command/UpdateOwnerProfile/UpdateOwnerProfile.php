<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\UpdateOwnerProfile;

use App\Shared\Application\Bus\CommandInterface;

/**
 * The owner's edit of the Portal profile (`PATCH /me`): each null part is
 * left as it is. The clinics' client files are not touched.
 */
final readonly class UpdateOwnerProfile implements CommandInterface
{
    public function __construct(
        public string $userId,
        public ?string $firstName = null,
        public ?string $lastName = null,
        public ?ProfileAddress $address = null,
    ) {
    }
}
