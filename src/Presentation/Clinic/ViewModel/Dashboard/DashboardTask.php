<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Dashboard;

/**
 * Something waiting to be dealt with, raised automatically from the clinic's
 * data (a patient to check out, a stock alert, unpaid invoices, a
 * consultation left open).
 *
 * `audience` is who the task is for (`clinique`: everyone, `asv`: assistants
 * and front desk, `veto`: veterinarians); `assigneeUserId` narrows it to one
 * member. `forms` replaces the action link with buttons that post (« Confirmer »
 * / « Refuser » an online request), each with the `appointment` token.
 */
final readonly class DashboardTask
{
    /**
     * @param 'urgent'|'jour'|'fond'                                $group
     * @param 'labo'|'cr'|'vaccin'|'fact'|'stock'|'rdv'|'portal'    $icon
     * @param 'clinique'|'asv'|'veto'                               $audience
     * @param list<array{label: string, url: string, danger: bool}> $forms
     */
    public function __construct(
        public string $group,
        public string $icon,
        public string $title,
        public string $subtitle,
        public string $audience,
        public ?string $assigneeUserId,
        public string $actionLabel,
        public string $actionUrl,
        public array $forms = [],
    ) {
    }
}
