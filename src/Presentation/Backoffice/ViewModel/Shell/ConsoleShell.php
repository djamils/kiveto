<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\ViewModel\Shell;

use App\Presentation\Backoffice\Security\ConsoleAdmin;

/**
 * What the black top bar and the tab bar show.
 */
final readonly class ConsoleShell
{
    /**
     * @param list<ConsoleTab> $tabs
     */
    public function __construct(
        public string $tierLabel,
        public string $tierModifier,
        public ?ConsoleAdmin $admin,
        public array $tabs,
        public ?string $searchUrl,
        public ?string $accountUrl,
        public ?string $translationsUrl,
        public ?string $auditUrl,
    ) {
    }
}
