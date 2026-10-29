<?php

declare(strict_types=1);

namespace App\System\Notification\Infrastructure\Persistence\Doctrine\Repository;

use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use App\System\Notification\Domain\Clinic\ClinicMessagingProfile;
use App\System\Notification\Domain\Clinic\ClinicMessagingProfileRepositoryInterface;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

/**
 * Profiles are upserted (Clinic pushes the whole profile) and may be read
 * with a row lock (`FOR UPDATE`), held until the end of the transaction, to
 * serialise the SMS cap check of a clinic.
 */
final readonly class DbalClinicMessagingProfileRepository implements ClinicMessagingProfileRepositoryInterface
{
    private const string TABLE = 'notification__clinic_messaging_profiles';

    public function __construct(
        private Connection $connection,
        private ClockInterface $clock,
    ) {
    }

    public function save(ClinicMessagingProfile $profile): void
    {
        $this->connection->executeStatement(
            'INSERT INTO ' . self::TABLE . ' (clinic_id, name, email_local_part, reply_to, sms_sender, overage_cap_segments,'
            . ' address_line, city, phone_display, brand_color, logo_storage_key, updated_at)'
            . ' VALUES (:clinic, :name, :local, :replyTo, :sms, :cap, :address, :city, :phone, :color, :logo, :at)'
            . ' AS new ON DUPLICATE KEY UPDATE name = new.name, email_local_part = new.email_local_part,'
            . ' reply_to = new.reply_to, sms_sender = new.sms_sender, overage_cap_segments = new.overage_cap_segments,'
            . ' address_line = new.address_line, city = new.city, phone_display = new.phone_display,'
            . ' brand_color = new.brand_color, logo_storage_key = new.logo_storage_key, updated_at = new.updated_at',
            [
                'clinic'  => Uuid::fromString($profile->clinicId)->toBinary(),
                'name'    => $profile->name,
                'local'   => $profile->emailLocalPart,
                'replyTo' => $profile->replyTo,
                'sms'     => $profile->smsSender,
                'cap'     => $profile->overageCapSegments,
                'address' => $profile->addressLine,
                'city'    => $profile->city,
                'phone'   => $profile->phoneDisplay,
                'color'   => $profile->brandColor,
                'logo'    => $profile->logoStorageKey,
                'at'      => $this->clock->now()->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            ],
        );
    }

    public function find(string $clinicId): ?ClinicMessagingProfile
    {
        return $this->fetch($clinicId, '');
    }

    public function findForUpdate(string $clinicId): ?ClinicMessagingProfile
    {
        return $this->fetch($clinicId, ' FOR UPDATE');
    }

    private function fetch(string $clinicId, string $lock): ?ClinicMessagingProfile
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM ' . self::TABLE . ' WHERE clinic_id = :clinic' . $lock,
            ['clinic' => Uuid::fromString($clinicId)->toBinary()],
        );

        if (false === $row) {
            return null;
        }

        return ClinicMessagingProfile::of(
            clinicId: $clinicId,
            name: RowAccessor::string($row, 'name'),
            emailLocalPart: RowAccessor::string($row, 'email_local_part'),
            replyTo: RowAccessor::nullableString($row, 'reply_to'),
            smsSender: RowAccessor::string($row, 'sms_sender'),
            overageCapSegments: RowAccessor::int($row, 'overage_cap_segments'),
            addressLine: RowAccessor::string($row, 'address_line'),
            city: RowAccessor::string($row, 'city'),
            phoneDisplay: RowAccessor::string($row, 'phone_display'),
            brandColor: RowAccessor::nullableString($row, 'brand_color'),
            logoStorageKey: RowAccessor::nullableString($row, 'logo_storage_key'),
        );
    }
}
