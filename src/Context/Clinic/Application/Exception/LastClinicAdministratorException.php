<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Exception;

final class LastClinicAdministratorException extends \RuntimeException
{
    public function __construct(string $clinicId)
    {
        parent::__construct(\sprintf(
            'Clinic "%s" would be left without anyone able to administer it.',
            $clinicId,
        ));
    }
}
