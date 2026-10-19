<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Twig;

/**
 * Formats an E.164 phone number for display: flag icon + formatted number.
 *
 * Two modes:
 *   |phone_display        → local format with trunk prefix (🇫🇷 06 12 34 56 78)
 *   |phone_display(true)  → international with dial code, no trunk (🇫🇷 +33 6 12 34 56 78)
 *
 * Italy has no trunk prefix: its leading 0 is part of the number (+39 06 1234567).
 *
 * Country data mirrors the JS COUNTRIES array in phone_input_controller.js.
 */
final class PhoneFormatRuntime
{
    /**
     * Sorted by dialCode length descending for longest-match-first detection.
     * maskLocal: mask for local display (with trunk prefix).
     * maskIntl: mask for international display (without trunk prefix).
     *
     * @var list<array{
     *   dialCode: string, code: string, trunkPrefix: string,
     *   maskLocal: string, maskIntl: string
     * }>
     */
    private const array COUNTRIES = [
        ['dialCode'     => '+352', 'code' => 'lu', 'trunkPrefix' => '',
            'maskLocal' => '### ### ###',    'maskIntl' => '### ### ###'],
        ['dialCode'     => '+212', 'code' => 'ma', 'trunkPrefix' => '0',
            'maskLocal' => '## ## ## ## ##', 'maskIntl' => '# ## ## ## ##'],
        ['dialCode'     => '+216', 'code' => 'tn', 'trunkPrefix' => '',
            'maskLocal' => '## ### ###',     'maskIntl' => '## ### ###'],
        ['dialCode'     => '+213', 'code' => 'dz', 'trunkPrefix' => '0',
            'maskLocal' => '### ## ## ##',   'maskIntl' => '## ## ## ##'],
        ['dialCode'     => '+33',  'code' => 'fr', 'trunkPrefix' => '0',
            'maskLocal' => '## ## ## ## ##', 'maskIntl' => '# ## ## ## ##'],
        ['dialCode'     => '+32',  'code' => 'be', 'trunkPrefix' => '0',
            'maskLocal' => '### ## ## ##',   'maskIntl' => '## ## ## ##'],
        ['dialCode'     => '+41',  'code' => 'ch', 'trunkPrefix' => '0',
            'maskLocal' => '## ### ## ##',   'maskIntl' => '# ### ## ##'],
        ['dialCode'     => '+34',  'code' => 'es', 'trunkPrefix' => '',
            'maskLocal' => '### ## ## ##',   'maskIntl' => '### ## ## ##'],
        ['dialCode'     => '+39',  'code' => 'it', 'trunkPrefix' => '',
            'maskLocal' => '### ### ####',   'maskIntl' => '### ### ####'],
        ['dialCode'     => '+49',  'code' => 'de', 'trunkPrefix' => '0',
            'maskLocal' => '### #######',    'maskIntl' => '## #######'],
        ['dialCode'     => '+44',  'code' => 'gb', 'trunkPrefix' => '0',
            'maskLocal' => '#### ### ####',  'maskIntl' => '#### ### ###'],
        ['dialCode'     => '+1',   'code' => 'us', 'trunkPrefix' => '',
            'maskLocal' => '(###) ###-####', 'maskIntl' => '(###) ###-####'],
    ];

    /**
     * @param bool $international true = show dial code, strip trunk prefix
     * @param bool $showFlag      false = number only, no flag icon
     */
    public function phoneDisplay(mixed $value, bool $international = false, bool $showFlag = true): string
    {
        if (!\is_string($value)) {
            return '';
        }

        if ('' === $value || !str_starts_with($value, '+')) {
            return htmlspecialchars($value, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
        }

        $country = $this->detectCountry($value);

        if (null === $country) {
            return htmlspecialchars($value, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
        }

        $national = substr($value, \strlen($country['dialCode']));

        if ($international) {
            $digits = $national;
            $mask   = $country['maskIntl'];
        } else {
            $digits = '' !== $country['trunkPrefix']
                ? $country['trunkPrefix'] . $national
                : $national;
            $mask = $country['maskLocal'];
        }

        $formatted = $this->applyMask($digits, $mask);
        $escaped   = htmlspecialchars($formatted, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');

        if (!$showFlag) {
            if ($international) {
                $dialCode = htmlspecialchars($country['dialCode'], \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');

                return $dialCode . ' ' . $escaped;
            }

            return $escaped;
        }

        $style = 'width:16px;height:12px;border-radius:2px;display:inline-block;vertical-align:-1px;margin-right:6px;';
        $flag  = '<span class="fi fi-' . $country['code'] . '" style="' . $style . '"></span>';

        if ($international) {
            $dialCode = htmlspecialchars($country['dialCode'], \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');

            return $flag . $dialCode . ' ' . $escaped;
        }

        return $flag . $escaped;
    }

    /**
     * @return array{
     *   dialCode: string, code: string, trunkPrefix: string,
     *   maskLocal: string, maskIntl: string
     * }|null
     */
    private function detectCountry(string $e164): ?array
    {
        foreach (self::COUNTRIES as $country) {
            if (str_starts_with($e164, $country['dialCode'])) {
                return $country;
            }
        }

        return null;
    }

    private function applyMask(string $digits, string $mask): string
    {
        $result = '';
        $di     = 0;
        $len    = \strlen($mask);

        for ($i = 0; $i < $len && $di < \strlen($digits); ++$i) {
            if ('#' === $mask[$i]) {
                $result .= $digits[$di++];
            } else {
                $result .= $mask[$i];
            }
        }

        if ($di < \strlen($digits)) {
            $result .= substr($digits, $di);
        }

        return $result;
    }
}
