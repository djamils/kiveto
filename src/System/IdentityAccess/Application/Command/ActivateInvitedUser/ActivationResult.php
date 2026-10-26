<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\ActivateInvitedUser;

enum ActivationResult: string
{
    case ACTIVATED = 'ACTIVATED';
    // A link of another clinic, for an account activated since: « déjà actif — connectez-vous ».
    case ALREADY_ACTIVE = 'ALREADY_ACTIVE';
}
