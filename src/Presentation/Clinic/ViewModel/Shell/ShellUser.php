<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Shell;

/**
 * The signed-in member, as the topbar avatar and the profile menu show them.
 */
final readonly class ShellUser
{
    public function __construct(
        public string $name,
        public string $initials,
        public string $email,
        public ?string $roleLabel,
    ) {
    }
}
