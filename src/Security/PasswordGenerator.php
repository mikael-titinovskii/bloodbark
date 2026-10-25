<?php

namespace App\Security;

/**
 * Passwords are generated, never chosen: the account is created with one and it reaches the user
 * by e-mail. The alphabet drops the characters that are hard to tell apart when retyped from a
 * message (l, 1, I, O, 0) and carries no punctuation, which mail clients and copy-paste mangle
 * more often than they mangle letters.
 *
 * Twenty characters out of this 56-symbol alphabet is ~116 bits; the guaranteed mix of cases and
 * digits keeps the result inside any sane password policy as well.
 */
final class PasswordGenerator
{
    public const int LENGTH = 20;
    public const string LOWER = 'abcdefghijkmnpqrstuvwxyz';
    public const string UPPER = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    public const string DIGITS = '23456789';
    public const string ALPHABET = self::LOWER.self::UPPER.self::DIGITS;

    public function generate(): string
    {
        $characters = [
            self::pick(self::UPPER),
            self::pick(self::LOWER),
            self::pick(self::DIGITS),
            self::pick(self::DIGITS),
        ];

        while (\count($characters) < self::LENGTH) {
            $characters[] = self::pick(self::ALPHABET);
        }

        shuffle($characters);

        return implode('', $characters);
    }

    private static function pick(string $alphabet): string
    {
        return $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
}
