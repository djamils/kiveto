<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Shell;

/**
 * Everything the application shell (sidebar, topbar, clinic switcher) shows
 * on every clinic page.
 */
final readonly class ShellView
{
    /**
     * @param list<ShellClinic> $clinics every clinic of the member, current one included, in switcher order
     */
    public function __construct(
        public ?ShellClinic $currentClinic,
        public array $clinics,
        public ShellUser $user,
        public string $todayLabel,
        public int $practitionerCount,
        public ?int $appointmentsToday,
        public ?bool $isOpenNow,
        public ?string $address,
        public int $stockAlertCount,
        // Unread bell entries of the member in the current clinic
        public int $unreadNotifications = 0,
    ) {
    }

    /**
     * @return list<ShellClinic>
     */
    public function otherClinics(): array
    {
        return array_values(array_filter(
            $this->clinics,
            static fn (ShellClinic $clinic): bool => !$clinic->isCurrent,
        ));
    }
}
