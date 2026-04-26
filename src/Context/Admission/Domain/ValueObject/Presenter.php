<?php

declare(strict_types=1);

namespace App\Context\Admission\Domain\ValueObject;

use App\Shared\Domain\ValueObject\PhoneNumber;

final class Presenter
{
    private string $name;

    private ?PhoneNumber $phone;

    private PresenterRole $role;

    private function __construct(string $name, ?PhoneNumber $phone, PresenterRole $role)
    {
        $this->name  = $name;
        $this->phone = $phone;
        $this->role  = $role;
    }

    public static function create(string $name, ?PhoneNumber $phone, PresenterRole $role): self
    {
        return new self($name, $phone, $role);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function phone(): ?PhoneNumber
    {
        return $this->phone;
    }

    public function role(): PresenterRole
    {
        return $this->role;
    }

    public function equals(self $other): bool
    {
        $phoneEquals = (null === $this->phone && null === $other->phone)
            || (null !== $this->phone && null !== $other->phone && $this->phone->equals($other->phone));

        return $this->name === $other->name
            && $this->role === $other->role
            && $phoneEquals;
    }
}
