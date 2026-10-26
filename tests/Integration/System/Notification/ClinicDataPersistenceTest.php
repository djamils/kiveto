<?php

declare(strict_types=1);

namespace App\Tests\Integration\System\Notification;

use App\System\Notification\Domain\Clinic\ClinicMessagingProfileRepositoryInterface;
use App\System\Notification\Domain\Quota\VeterinarianCountRepositoryInterface;
use App\System\Notification\Infrastructure\Persistence\Doctrine\Entity\ClinicMessagingProfileEntity;
use App\System\Notification\Infrastructure\Persistence\Doctrine\Entity\SmsThresholdCrossedEntity;
use App\System\Notification\Infrastructure\Persistence\Doctrine\Entity\VeterinarianCountEntity;
use App\Tests\Unit\System\Notification\Support\InMemoryProfiles;
use App\Tests\Unit\System\Notification\Support\Messages;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class ClinicDataPersistenceTest extends KernelTestCase
{
    public function testAProfileIsCreatedThenReplaced(): void
    {
        $profiles = $this->service(ClinicMessagingProfileRepositoryInterface::class);
        self::assertNull($profiles->find(Messages::CLINIC));

        $profiles->save(InMemoryProfiles::montmartre(logo: 'clinic/logo.png'));
        $profiles->save(InMemoryProfiles::montmartre(cap: 120));

        $profile = $profiles->findForUpdate(Messages::CLINIC);
        self::assertNotNull($profile);
        self::assertSame(['Clinique Montmartre', 'clinique-montmartre', 'accueil@clinique-montmartre.fr', 'Montmartre', 120], [$profile->name, $profile->emailLocalPart, $profile->replyTo, $profile->smsSender, $profile->overageCapSegments]);
        self::assertSame(['18 rue Lepic, 75018 Paris', 'Paris 18e', '01 42 64 18 00', '#4338CA', null], [$profile->addressLine, $profile->city, $profile->phoneDisplay, $profile->brandColor, $profile->logoStorageKey]);
        self::assertSame(Messages::CLINIC, $profiles->find(Messages::CLINIC)?->clinicId);
    }

    public function testVeterinarianCountsAreUpsertedPerDay(): void
    {
        $counts = $this->service(VeterinarianCountRepositoryInterface::class);
        self::assertNull($counts->maxBetween(Messages::CLINIC, new \DateTimeImmutable('2026-10-01'), new \DateTimeImmutable('2026-10-31')));
        self::assertNull($counts->lastKnownBefore(Messages::CLINIC, new \DateTimeImmutable('2026-10-01')));

        $counts->record(Messages::CLINIC, new \DateTimeImmutable('2026-09-30'), 5);
        $counts->record(Messages::CLINIC, new \DateTimeImmutable('2026-10-01'), 4);
        $counts->record(Messages::CLINIC, new \DateTimeImmutable('2026-10-01'), 2);
        $counts->record(Messages::CLINIC, new \DateTimeImmutable('2026-10-31'), 3);
        $counts->record('01960000-0000-7000-8000-00000000c22c', new \DateTimeImmutable('2026-10-15'), 9);

        self::assertSame(3, $counts->maxBetween(Messages::CLINIC, new \DateTimeImmutable('2026-10-01'), new \DateTimeImmutable('2026-10-31')));
        self::assertSame(5, $counts->lastKnownBefore(Messages::CLINIC, new \DateTimeImmutable('2026-10-01')));
        self::assertSame(3, $counts->lastKnownBefore(Messages::CLINIC, new \DateTimeImmutable('2026-12-01')));
    }

    public function testTheTableRowsHoldTheirColumns(): void
    {
        $id  = Uuid::fromString(Messages::CLINIC);
        $at  = new \DateTimeImmutable('2026-10-26 10:00:00');
        $day = new \DateTimeImmutable('2026-10-26');

        $profile = new ClinicMessagingProfileEntity($id, 'N', 'n', null, 'S', 1, 'A', 'C', 'P', null, null, $at);
        self::assertSame([$id, 'N', 'n', null, 'S', 1, 'A', 'C', 'P', null, null, $at], [
            $profile->getClinicId(), $profile->getName(), $profile->getEmailLocalPart(), $profile->getReplyTo(), $profile->getSmsSender(),
            $profile->getOverageCapSegments(), $profile->getAddressLine(), $profile->getCity(), $profile->getPhoneDisplay(),
            $profile->getBrandColor(), $profile->getLogoStorageKey(), $profile->getUpdatedAt(),
        ]);

        $count = new VeterinarianCountEntity($id, $day, 3);
        self::assertSame([$id, $day, 3], [$count->getClinicId(), $count->getDay(), $count->getCount()]);

        $threshold = new SmsThresholdCrossedEntity($id, '2026-10', '80', $at);
        self::assertSame([$id, '2026-10', '80', $at], [$threshold->getClinicId(), $threshold->getMonth(), $threshold->getThreshold(), $threshold->getCrossedAt()]);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        \assert($em instanceof EntityManagerInterface);
        $em->persist($threshold);
        $em->flush();
        $em->clear();
        self::assertNotNull($em->find(SmsThresholdCrossedEntity::class, ['clinicId' => $id, 'month' => '2026-10', 'threshold' => '80']));
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function service(string $class): object
    {
        $service = self::getContainer()->get('test.' . $class);
        \assert($service instanceof $class);

        return $service;
    }
}
