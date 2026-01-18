<?php

declare(strict_types=1);

namespace App\Fixtures\Client\Factory;

use App\Client\Domain\ValueObject\ContactLabel;
use App\Client\Domain\ValueObject\ContactMethodType;
use App\Client\Infrastructure\Persistence\Doctrine\Entity\ContactMethodEntity;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<ContactMethodEntity>
 */
final class ContactMethodEntityFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return ContactMethodEntity::class;
    }

    public function forClient(string $clientId): self
    {
        return $this->with(['clientId' => Uuid::fromString($clientId)]);
    }

    public function phone(?string $number = null): self
    {
        return $this->with([
            'type'  => ContactMethodType::PHONE,
            'value' => $number ?? self::faker()->numerify('+33 # ## ## ## ##'),
        ]);
    }

    public function email(?string $email = null): self
    {
        return $this->with([
            'type'  => ContactMethodType::EMAIL,
            'value' => $email ?? self::faker()->email(),
        ]);
    }

    public function mobile(): self
    {
        return $this->phone()->with(['label' => ContactLabel::MOBILE]);
    }

    public function home(): self
    {
        return $this->with(['label' => ContactLabel::HOME]);
    }

    public function work(): self
    {
        return $this->with(['label' => ContactLabel::WORK]);
    }

    public function primary(): self
    {
        return $this->with(['isPrimary' => true]);
    }

    protected function defaults(): array|callable
    {
        return [
            'id'       => Uuid::v7(),
            'clientId' => Uuid::v7(),
            'type'     => self::faker()->randomElement([ContactMethodType::PHONE, ContactMethodType::EMAIL]),
            'label'    => self::faker()->randomElement([
                ContactLabel::MOBILE,
                ContactLabel::HOME,
                ContactLabel::WORK,
                ContactLabel::OTHER,
            ]),
            'value' => fn (array $attributes) => ContactMethodType::EMAIL === $attributes['type']
                ? self::faker()->email()
                : self::faker()->numerify('+33 # ## ## ## ##'),
            'isPrimary' => false,
        ];
    }
}
