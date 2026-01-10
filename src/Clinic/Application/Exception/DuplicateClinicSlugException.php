<?php

declare(strict_types=1);

namespace App\Clinic\Application\Exception;

final class DuplicateClinicSlugException extends \RuntimeException
{
    public function __construct(string $slug)
    {
        parent::__construct(\sprintf('A clinic with slug "%s" already exists.', $slug));
    }
}
