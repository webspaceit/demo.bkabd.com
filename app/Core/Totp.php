<?php

namespace App\Core;

/**
 * RFC 6238 time-based one-time passwords (Google-Authenticator compatible),
 * pure PHP — the host has no Composer TOTP/QR dependencies.
 *
 *   $secret = Totp::generateSecret();                 // base32, 160 bits
 *   $uri    = Totp::provisioningUri($secret, $email); // otpauth://totp/...
 *   if (Totp::verify($secret, $code)) { /* accepted *\/ }
 *
 * Verification allows the current 30s time step plus ±1 step of clock skew
 * and compares codes with hash_equals (timing-safe).
 */
final class Totp
{
    /** @var int Seconds per time step (RFC 6238 default). */
    public const PERIOD = 30;

    /** @var int Digit length of the code. */
    public const DIGITS = 6;

    /** @var string RFC 4648 base32 alphabet (no padding). */
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /** New random shared secret (base32, no padding). */
    public static function generateSecret(int $bytes = 20): string
    {
        return self::base32Encode(random_bytes($bytes));
    }

    /** otpauth:// URI the authenticator app scans (period/digits/algorithm set explicitly). */
    public static function provisioningUri(string $secret, string $account, string $issuer = 'BKABD'): string
    {
        $label = rawurlencode($issuer) . ':' . rawurlencode($account);
        $params = http_build_query([
            'secret'    => $secret,
            'issuer'    => $issuer,
            'algorithm' => 'SHA1',
            'digits'    => self::DIGITS,
            'period'    => self::PERIOD,
        ]);
        return 'otpauth://totp/' . $label . '?' . $params;
    }

    /**
     * Verify a code within ±$window time steps of now.
     *
     * @param string $code 6 digits (non-digits are stripped first)
     */
    public static function verify(string $secret, string $code, int $window = 1): bool
    {
        $code = (string) preg_replace('/\D/', '', (string) $code);
        if ($code === '' || strlen($code) !== self::DIGITS) {
            return false;
        }
        $key = self::base32Decode(strtoupper(trim($secret)));
        if ($key === false || $key === '') {
            return false;
        }
        $counter = (int) floor(time() / self::PERIOD);
        for ($i = -$window; $i <= $window; $i++) {
            $expected = self::pad(self::hotp($key, $counter + $i, self::DIGITS), self::DIGITS);
            if (hash_equals($expected, $code)) {
                return true;
            }
        }
        return false;
    }

    /** HOTP (RFC 4226) building block — code for one specific counter. */
    private static function hotp(string $key, int $counter, int $digits): string
    {
        $binary = pack('N*', 0) . pack('N*', $counter); // 8-byte big-endian counter
        $hash = hash_hmac('sha1', $binary, $key, true);
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $value = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        ) % (10 ** $digits);
        return (string) $value;
    }

    private static function pad(string $code, int $digits): string
    {
        return str_pad($code, $digits, '0', STR_PAD_LEFT);
    }

    private static function base32Encode(string $bin): string
    {
        $out = '';
        $bits = 0;
        $carry = 0;
        foreach (str_split($bin) as $byte) {
            $carry = ($carry << 8) | ord($byte);
            $bits += 8;
            while ($bits >= 5) {
                $bits -= 5;
                $out .= self::ALPHABET[($carry >> $bits) & 0x1F];
            }
        }
        if ($bits > 0) {
            $out .= self::ALPHABET[($carry << (5 - $bits)) & 0x1F];
        }
        return $out;
    }

    /** @return string|false */
    private static function base32Decode(string $s)
    {
        $s = rtrim($s, '=');
        if ($s === '' || !preg_match('/^[A-Z2-7]+$/', $s)) {
            return false;
        }
        $out = '';
        $bits = 0;
        $carry = 0;
        foreach (str_split($s) as $char) {
            // 'A'-'Z' → 0..25, '2'-'7' → 26..31
            $carry = ($carry << 5) | (ord($char) - (ord($char) < ord('A') ? 24 : 65));
            $bits += 5;
            if ($bits >= 8) {
                $bits -= 8;
                $out .= chr(($carry >> $bits) & 0xFF);
            }
        }
        return $out;
    }
}