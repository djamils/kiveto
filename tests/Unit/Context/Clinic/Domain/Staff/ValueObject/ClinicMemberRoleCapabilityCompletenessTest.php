<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Domain\Staff\ValueObject;

use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;
use PHPUnit\Framework\TestCase;

final class ClinicMemberRoleCapabilityCompletenessTest extends TestCase
{
    /**
     * Explicit snapshot: role value →
     * [canHoldVeterinaryCredentials, canBePractitionerOfRecord, appearsInMedicalAgendaByDefault].
     * Updating this map is mandatory when adding a new ClinicMemberRole case.
     *
     * @var array<string, array{
     *   canHoldVeterinaryCredentials: bool,
     *   canBePractitionerOfRecord: bool,
     *   appearsInMedicalAgendaByDefault: bool
     * }>
     */
    private const array EXPECTED_CAPABILITIES = [
        'MANAGER' => [
            'canHoldVeterinaryCredentials'    => false,
            'canBePractitionerOfRecord'       => false,
            'appearsInMedicalAgendaByDefault' => false,
        ],
        'VETERINARY' => [
            'canHoldVeterinaryCredentials'    => true,
            'canBePractitionerOfRecord'       => true,
            'appearsInMedicalAgendaByDefault' => true,
        ],
        'VETERINARY_ASSISTANT' => [
            'canHoldVeterinaryCredentials'    => false,
            'canBePractitionerOfRecord'       => false,
            'appearsInMedicalAgendaByDefault' => false,
        ],
        'RECEPTIONIST' => [
            'canHoldVeterinaryCredentials'    => false,
            'canBePractitionerOfRecord'       => false,
            'appearsInMedicalAgendaByDefault' => false,
        ],
    ];

    public function testEveryRoleIsInSnapshot(): void
    {
        foreach (ClinicMemberRole::cases() as $case) {
            self::assertArrayHasKey(
                $case->value,
                self::EXPECTED_CAPABILITIES,
                \sprintf(
                    'ClinicMemberRole::%s is missing from the capabilities snapshot. '
                    . 'Add it to EXPECTED_CAPABILITIES in %s.',
                    $case->name,
                    self::class,
                ),
            );
        }
    }

    public function testSnapshotHasNoOrphanRoles(): void
    {
        $defined = array_map(static fn (ClinicMemberRole $r): string => $r->value, ClinicMemberRole::cases());

        foreach (array_keys(self::EXPECTED_CAPABILITIES) as $snapshotKey) {
            self::assertContains(
                $snapshotKey,
                $defined,
                \sprintf('Snapshot key "%s" has no matching ClinicMemberRole case.', $snapshotKey),
            );
        }
    }

    public function testCapabilitiesMatchSnapshot(): void
    {
        foreach (ClinicMemberRole::cases() as $case) {
            $expected = self::EXPECTED_CAPABILITIES[$case->value];

            self::assertSame(
                $expected['canHoldVeterinaryCredentials'],
                $case->canHoldVeterinaryCredentials(),
                \sprintf('canHoldVeterinaryCredentials() mismatch for role %s.', $case->value),
            );

            self::assertSame(
                $expected['canBePractitionerOfRecord'],
                $case->canBePractitionerOfRecord(),
                \sprintf('canBePractitionerOfRecord() mismatch for role %s.', $case->value),
            );

            self::assertSame(
                $expected['appearsInMedicalAgendaByDefault'],
                $case->appearsInMedicalAgendaByDefault(),
                \sprintf('appearsInMedicalAgendaByDefault() mismatch for role %s.', $case->value),
            );
        }
    }
}
