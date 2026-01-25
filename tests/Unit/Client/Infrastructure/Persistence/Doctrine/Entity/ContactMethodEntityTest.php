<?php

declare(strict_types=1);

namespace App\Tests\Unit\Client\Infrastructure\Persistence\Doctrine\Entity;

use App\Client\Domain\ValueObject\ContactLabel;
use App\Client\Domain\ValueObject\ContactMethodType;
use App\Client\Infrastructure\Persistence\Doctrine\Entity\ContactMethodEntity;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class ContactMethodEntityTest extends TestCase
{
    public function testGettersAndSetters(): void
    {
        $entity = new ContactMethodEntity();

        $id       = Uuid::v7();
        $clientId = Uuid::v7();

        $entity->setId($id);
        $entity->setClientId($clientId);
        $entity->setType(ContactMethodType::PHONE);
        $entity->setLabel(ContactLabel::MOBILE);
        $entity->setValue('+33612345678');
        $entity->setIsPrimary(true);

        self::assertSame($id, $entity->getId());
        self::assertSame($clientId, $entity->getClientId());
        self::assertSame(ContactMethodType::PHONE, $entity->getType());
        self::assertSame(ContactLabel::MOBILE, $entity->getLabel());
        self::assertSame('+33612345678', $entity->getValue());
        self::assertTrue($entity->isPrimary());
    }

    public function testNonPrimaryContactMethod(): void
    {
        $entity = new ContactMethodEntity();
        $entity->setIsPrimary(false);

        self::assertFalse($entity->isPrimary());
    }

    public function testEmailContactMethod(): void
    {
        $entity = new ContactMethodEntity();

        $entity->setType(ContactMethodType::EMAIL);
        $entity->setLabel(ContactLabel::WORK);
        $entity->setValue('john@example.com');
        $entity->setIsPrimary(false);

        self::assertSame(ContactMethodType::EMAIL, $entity->getType());
        self::assertSame(ContactLabel::WORK, $entity->getLabel());
        self::assertSame('john@example.com', $entity->getValue());
        self::assertFalse($entity->isPrimary());
    }
}
