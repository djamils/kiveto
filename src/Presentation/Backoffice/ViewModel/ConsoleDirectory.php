<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\ViewModel;

use App\Context\Clinic\Application\Query\Console\ConsoleClinicRow;
use App\Context\Clinic\Application\Query\Console\ConsoleMemberRow;
use App\Context\Clinic\Application\Query\Console\ListConsoleClinics;
use App\Context\Clinic\Application\Query\Console\ListConsoleMembers;
use App\Shared\Application\Bus\QueryBusInterface;
use App\System\IdentityAccess\Application\Query\GetAccountSummaries\AccountSummary;
use App\System\IdentityAccess\Application\Query\GetAccountSummaries\GetAccountSummaries;
use App\System\IdentityAccess\Application\Query\GetInvitationStatuses\GetInvitationStatuses;

/**
 * Who works where, as the console reads it: the clinics and their members
 * (Clinic), the accounts' e-mails and sign-ins and the invitation links
 * (IdentityAccess), put side by side.
 */
final readonly class ConsoleDirectory
{
    public function __construct(
        private QueryBusInterface $queryBus,
    ) {
    }

    /**
     * @return list<ConsoleClinicRow>
     */
    public function clinics(?string $search = null): array
    {
        $rows = [];
        foreach (self::items($this->queryBus->ask(new ListConsoleClinics(search: $search))) as $row) {
            if ($row instanceof ConsoleClinicRow) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    public function clinic(string $clinicId): ?ConsoleClinicRow
    {
        $rows = self::items($this->queryBus->ask(new ListConsoleClinics(clinicId: $clinicId)));
        $row  = reset($rows);

        return $row instanceof ConsoleClinicRow ? $row : null;
    }

    /**
     * @return list<ConsoleMemberRow>
     */
    public function members(?string $clinicId = null, ?string $userId = null): array
    {
        $rows = [];
        foreach (self::items($this->queryBus->ask(new ListConsoleMembers($clinicId, $userId))) as $row) {
            if ($row instanceof ConsoleMemberRow) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * @param list<string> $userIds
     *
     * @return array<string, AccountSummary>
     */
    public function accounts(array $userIds): array
    {
        if ([] === $userIds) {
            return [];
        }

        $accounts = [];
        foreach (self::items($this->queryBus->ask(new GetAccountSummaries(array_values(array_unique($userIds))))) as $account) {
            if ($account instanceof AccountSummary) {
                $accounts[$account->id] = $account;
            }
        }

        return $accounts;
    }

    /**
     * userId → PENDING_VALID | PENDING_EXPIRED | ACTIVE for this clinic.
     *
     * @param list<string> $userIds
     *
     * @return array<string, string>
     */
    public function invitations(string $clinicId, array $userIds): array
    {
        if ([] === $userIds) {
            return [];
        }

        $statuses = [];
        foreach (self::items($this->queryBus->ask(new GetInvitationStatuses($clinicId, array_values(array_unique($userIds))))) as $userId => $status) {
            if (\is_string($status)) {
                $statuses[(string) $userId] = $status;
            }
        }

        return $statuses;
    }

    /**
     * The state of a member as the console prints it.
     *
     * @return array{key: string, label: string, badge: string}
     */
    public static function memberState(ConsoleMemberRow $member, ?AccountSummary $account, ?string $invitation): array
    {
        return match (true) {
            $member->isDisabled() || (null !== $account && $account->isDisabled()) => ['key' => 'disabled', 'label' => 'Désactivé', 'badge' => 'is-outline'],
            GetInvitationStatuses::PENDING_VALID === $invitation                   => ['key' => 'invited', 'label' => 'Invitation envoyée', 'badge' => 'is-orange'],
            GetInvitationStatuses::PENDING_EXPIRED === $invitation                 => ['key' => 'expired', 'label' => 'Invitation expirée', 'badge' => 'is-red'],
            default                                                                => ['key' => 'active', 'label' => 'Actif', 'badge' => 'is-green'],
        };
    }

    public static function roleLabel(string $role): string
    {
        return match ($role) {
            'VETERINARY'           => 'Vétérinaire',
            'VETERINARY_ASSISTANT' => 'ASV',
            'RECEPTIONIST'         => 'Accueil',
            'MANAGER'              => 'Gérant',
            default                => $role,
        };
    }

    /**
     * @return array<mixed>
     */
    private static function items(mixed $answer): array
    {
        return \is_array($answer) ? $answer : [];
    }
}
