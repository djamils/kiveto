<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Client;

use App\Context\Client\Application\Command\ReplaceClientContactMethods\ContactMethodDto as ContactMethodInput;
use App\Context\Client\Application\Query\GetClientById\ContactMethodDto;

/**
 * The client sheet edits three contact slots (main phone, other phone, e-mail) while
 * a client may hold more contact methods than that. This maps the list onto
 * the slots and back, leaving the methods the sheet does not show untouched.
 */
final readonly class ClientContactSlots
{
    /**
     * @param list<ContactMethodDto> $others
     */
    private function __construct(
        public ?ContactMethodDto $mobile,
        public ?ContactMethodDto $landline,
        public ?ContactMethodDto $email,
        private array $others,
    ) {
    }

    /**
     * @param list<ContactMethodDto> $contactMethods
     */
    public static function fromContactMethods(array $contactMethods): self
    {
        $phones = array_values(array_filter($contactMethods, static fn (ContactMethodDto $m): bool => 'phone' === $m->type));
        $emails = array_values(array_filter($contactMethods, static fn (ContactMethodDto $m): bool => 'email' === $m->type));

        $mobile = self::first($phones, static fn (ContactMethodDto $m): bool => $m->isPrimary)
            ?? self::first($phones, static fn (ContactMethodDto $m): bool => 'mobile' === $m->label)
            ?? $phones[0] ?? null;

        $otherPhones = array_values(array_filter($phones, static fn (ContactMethodDto $m): bool => $m !== $mobile));
        $landline    = self::first($otherPhones, static fn (ContactMethodDto $m): bool => 'home' === $m->label)
            ?? $otherPhones[0] ?? null;

        $email = self::first($emails, static fn (ContactMethodDto $m): bool => $m->isPrimary) ?? $emails[0] ?? null;

        return new self(
            $mobile,
            $landline,
            $email,
            array_values(array_filter(
                $contactMethods,
                static fn (ContactMethodDto $m): bool => $m !== $mobile && $m !== $landline && $m !== $email,
            )),
        );
    }

    public function hasOthers(): bool
    {
        return [] !== $this->others;
    }

    /**
     * @return list<ContactMethodInput>
     */
    public function withValues(?string $mobile, ?string $landline, ?string $email): array
    {
        $methods = [];

        if (null !== $mobile) {
            $methods[] = new ContactMethodInput('phone', $this->mobile->label ?? 'mobile', $mobile, true);
        }

        if (null !== $landline) {
            // Left alone, the second number becomes the one the clinic calls first.
            $methods[] = new ContactMethodInput('phone', $this->landline->label ?? 'home', $landline, null === $mobile);
        }

        if (null !== $email) {
            $methods[] = new ContactMethodInput('email', $this->email->label ?? 'home', $email, true);
        }

        foreach ($this->others as $other) {
            foreach ($methods as $method) {
                // A value typed into a slot wins over the same value kept aside.
                if ($method->type === $other->type && mb_strtolower($method->value) === mb_strtolower($other->value)) {
                    continue 2;
                }
            }

            $methods[] = new ContactMethodInput($other->type, $other->label, $other->value, false);
        }

        return $methods;
    }

    /**
     * @param list<ContactMethodDto>           $methods
     * @param callable(ContactMethodDto): bool $matches
     */
    private static function first(array $methods, callable $matches): ?ContactMethodDto
    {
        foreach ($methods as $method) {
            if ($matches($method)) {
                return $method;
            }
        }

        return null;
    }
}
