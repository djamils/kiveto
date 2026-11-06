<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Port;

use App\Context\Clinic\Application\Query\Console\ConsoleClinicRow;
use App\Context\Clinic\Application\Query\Console\ConsoleMemberRow;

/**
 * The read side of the console over the Clinic tables only (clinics,
 * accounts, memberships, staff profiles). Accounts' e-mails and sign-ins
 * belong to IdentityAccess: the console asks it separately.
 */
interface ClinicConsoleReadRepositoryInterface
{
    /**
     * Every clinic but the closed ones, by name.
     *
     * @return list<ConsoleClinicRow>
     */
    public function clinics(): array;

    public function clinic(string $clinicId): ?ConsoleClinicRow;

    /**
     * The clinics whose name, tenant id, SIRET or legal name contain `text`.
     *
     * @return list<ConsoleClinicRow>
     */
    public function searchClinics(string $text, int $limit): array;

    /**
     * Memberships with their profile, by clinic name then person; filtered
     * by clinic and/or account when given.
     *
     * @return list<ConsoleMemberRow>
     */
    public function members(?string $clinicId = null, ?string $userId = null): array;
}
