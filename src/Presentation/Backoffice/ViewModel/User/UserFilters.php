<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\ViewModel\User;

use App\Presentation\Backoffice\ViewModel\ConsoleDirectory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Uid\Uuid;

/**
 * The filters of the users list, read from its address and written back
 * into its links (pager, pills, export).
 */
final readonly class UserFilters
{
    public function __construct(
        public string $tab,
        public string $status,
        public ?string $clinicId,
        public string $search,
    ) {
    }

    public static function fromRequest(Request $request, ConsoleDirectory $directory): self
    {
        $tab    = 'portal' === $request->query->getString('tab') ? 'portal' : 'staff';
        $status = $request->query->getString('status', 'all');
        $clinic = $request->query->getString('clinic');

        return new self(
            tab: $tab,
            status: \in_array($status, UserListBuilder::STATUSES, true) ? $status : 'all',
            clinicId: Uuid::isValid($clinic) && null !== $directory->clinic($clinic) ? $clinic : null,
            search: trim($request->query->getString('q')),
        );
    }

    /**
     * The address parameters, defaults left out.
     *
     * @return array<string, string>
     */
    public function params(): array
    {
        return array_filter([
            'tab'    => 'portal' === $this->tab ? 'portal' : null,
            'status' => 'staff' === $this->tab && 'all' !== $this->status ? $this->status : null,
            'clinic' => 'staff' === $this->tab ? $this->clinicId : null,
            'q'      => '' !== $this->search ? $this->search : null,
        ], static fn (?string $value): bool => null !== $value);
    }
}
