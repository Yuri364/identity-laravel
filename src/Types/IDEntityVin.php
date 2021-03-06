<?php

declare(strict_types = 1);

namespace AvtoDev\IDEntity\Types;

use Exception;
use Illuminate\Support\Str;
use AvtoDev\IDEntity\Helpers\Transliterator;
use AvtoDev\ExtendedLaravelValidator\Extensions\VinCodeValidatorExtension;

class IDEntityVin extends AbstractTypedIDEntity
{
    /**
     * {@inheritdoc}
     */
    public function getType(): string
    {
        return static::ID_TYPE_VIN;
    }

    /**
     * {@inheritdoc}
     */
    public static function normalize($value): ?string
    {
        try {
            // Производим замену кириллических символов на латинские аналоги.
            $value = Transliterator::transliterateString(Str::upper($value), true);

            // Латинская "O" заменяется на ноль
            $value = \str_replace('O', '0', $value);

            // Удаляем все символы, кроме разрешенных
            $value = \preg_replace('~[^ABCDEFGHJKLMNPRSTUVWXYZ0-9]~u', '', $value);

            return $value;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Validate VIN code checksum.
     *
     * @see https://en.wikipedia.org/wiki/Vehicle_identification_number
     *
     * @return bool
     */
    public function isChecksumValidated(): bool
    {
        static $weights = [8, 7, 6, 5, 4, 3, 2, 10, 0, 9, 8, 7, 6, 5, 4, 3, 2];

        static $transliterations = [
            'a' => 1, 'b' => 2, 'c' => 3, 'd' => 4, 'e' => 5, 'f' => 6, 'g' => 7, 'h' => 8, 'j' => 1, 'k' => 2,
            'l' => 3, 'm' => 4, 'n' => 5, 'p' => 7, 'r' => 9, 's' => 2, 't' => 3, 'u' => 4, 'v' => 5, 'w' => 6,
            'x' => 7, 'y' => 8, 'z' => 9,
        ];

        $characters = (array) \str_split(Str::lower((string) $this->value));
        $length     = \count($characters);
        $sum        = 0;

        if ($length !== 17) {
            return false;
        }

        for ($i = 0; $i < $length; $i++) {
            $sum += \is_numeric($characters[$i])
                ? $characters[$i] * $weights[$i]
                : ($transliterations[$characters[$i]] ?? 0) * $weights[$i];
        }

        $check_digit = $sum % 11;

        if ($check_digit === 10) {
            $check_digit = 'x';
        }

        return (string) $check_digit === (string) $characters[8];
    }

    /**
     * {@inheritdoc}
     */
    public function isValid(): bool
    {
        /** @var VinCodeValidatorExtension $validator */
        $validator = static::getContainer()->make(VinCodeValidatorExtension::class);

        return \is_string($this->value) && $validator->passes('', $this->value);
    }
}
