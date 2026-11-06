<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Security;

use App\Presentation\Backoffice\ViewModel\ConsoleFormat;

/**
 * The member of the Kiveto team signed in to the console.
 */
final readonly class ConsoleAdmin
{
    public function __construct(
        public string $id,
        public string $email,
        public string $name,
        public \DateTimeZone $timeZone,
    ) {
    }

    public function initials(): string
    {
        return ConsoleFormat::initials($this->name);
    }
}
