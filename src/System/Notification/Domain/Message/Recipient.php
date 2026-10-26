<?php

declare(strict_types=1);

namespace App\System\Notification\Domain\Message;

/**
 * Who a message goes to: an e-mail address or an E.164 mobile number, and
 * the name to greet or display.
 */
final readonly class Recipient
{
    private function __construct(
        public string $address,
        public string $name,
    ) {
    }

    public static function for(Channel $channel, string $address, string $name): self
    {
        $address = mb_trim($address);
        $valid   = match ($channel) {
            Channel::EMAIL => false !== filter_var($address, \FILTER_VALIDATE_EMAIL),
            Channel::SMS   => 1 === preg_match('/^\+[1-9]\d{6,14}$/', $address),
        };

        if (!$valid) {
            throw new \InvalidArgumentException(\sprintf('Invalid %s recipient "%s".', $channel->value, $address));
        }

        return new self($address, mb_trim($name));
    }

    public static function reconstitute(string $address, string $name): self
    {
        return new self($address, $name);
    }

    /** The name when there is one, else the address. */
    public function label(): string
    {
        return '' !== $this->name ? $this->name : $this->address;
    }
}
