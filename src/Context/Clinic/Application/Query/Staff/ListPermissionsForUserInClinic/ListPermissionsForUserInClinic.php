<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Query\Staff\ListPermissionsForUserInClinic;

use App\Shared\Application\Bus\QueryInterface;

/**
 * What a user is allowed to do in a clinic. Answers with an empty list when the
 * user does not work there, or no longer does.
 */
final readonly class ListPermissionsForUserInClinic implements QueryInterface
{
    public function __construct(
        public string $userId,
        public string $clinicId,
    ) {
    }
}
