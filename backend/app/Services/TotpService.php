<?php

namespace App\Services;

use Illuminate\Support\Str;

class TotpService
{
    private const BASE32_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Generate a new random Base32 secret key.
     */
    public static function generateSecret(int $length = 16): string
    {
        $secret = '';
        $maxIndex = strlen(self::BASE32_CHARS) - 1;
        for ($i = 0; $i < $length; $i++) {
            $secret .= self::BASE32_CHARS[random_int(0, $maxIndex)];
        }
        return $secret;
    }

    /**
     * Decode a Base32 string into binary bytes (RFC 4648).
     */
    public static function base32Decode(string $base32): string
    {
        $base32 = strtoupper(trim($base32));
        $base32 = rtrim($base32, '=');

        $buffer = 0;
        $bitsLeft = 0;
        $binary = '';

        for ($i = 0; $i < strlen($base32); $i++) {
            $char = $base32[$i];
            $val = strpos(self::BASE32_CHARS, $char);
            if ($val === false) {
                continue;
            }

            $buffer = ($buffer << 5) | $val;
            $bitsLeft += 5;

            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $binary .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }

        return $binary;
    }

    /**
     * Calculate 6-digit TOTP code for a given secret and 30-second time slice (RFC 6238).
     */
    public static function calculateCode(string $secret, int $timeSlice): string
    {
        $binaryKey = self::base32Decode($secret);
        
        // Pack 64-bit integer into 8 bytes big-endian
        $binaryTime = pack('N*', 0) . pack('N*', $timeSlice);

        // HMAC-SHA1
        $hash = hash_hmac('sha1', $binaryTime, $binaryKey, true);

        // Dynamic truncation (RFC 4226)
        $offset = ord($hash[19]) & 0x0F;
        $binaryCode = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        );

        $otp = $binaryCode % 1000000;

        return str_pad((string) $otp, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Generate the current 6-digit TOTP code for a given secret.
     */
    public static function generateCode(string $secret): string
    {
        $timeSlice = (int) floor(time() / 30);
        return self::calculateCode($secret, $timeSlice);
    }

    /**
     * Verify a 6-digit TOTP code with time drift window.
     *
     * @param string $secret Base32 encoded secret
     * @param string $code User-provided 6-digit code
     * @param int $window Tolerance window (+/- 30 seconds per step, default 1 = +/-30s per RFC 6238)
     * @return bool
     */
    public static function verify(string $secret, string $code, int $window = 1): bool
    {
        // Strip any whitespace, dashes, or non-digits for standard 6-digit TOTP
        $cleanCode = preg_replace('/\D/', '', $code);
        if (strlen($cleanCode) !== 6) {
            return false;
        }

        $currentTime = time();

        for ($i = -$window; $i <= $window; $i++) {
            $timeSlice = (int) floor(($currentTime + ($i * 30)) / 30);
            $calculated = self::calculateCode($secret, $timeSlice);
            if (hash_equals($calculated, $cleanCode)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build the standard otpauth URI for authenticator apps (Google Authenticator, Microsoft Authenticator, etc.).
     */
    public static function getOtpAuthUrl(string $company, string $accountName, string $secret): string
    {
        $encodedCompany = rawurlencode($company);
        $encodedAccount = rawurlencode($accountName);
        $encodedSecret = rawurlencode(strtoupper(trim($secret)));

        return "otpauth://totp/{$encodedCompany}:{$encodedAccount}?secret={$encodedSecret}&issuer={$encodedCompany}&algorithm=SHA1&digits=6&period=30";
    }

    /**
     * Generate backup recovery codes (8 codes formatted as XXXX-XXXX).
     */
    public static function generateRecoveryCodes(int $amount = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $amount; $i++) {
            $part1 = strtoupper(Str::random(4));
            $part2 = strtoupper(Str::random(4));
            $codes[] = "{$part1}-{$part2}";
        }
        return $codes;
    }

    /**
     * Hash a recovery code for secure persistent storage.
     * Strips dashes and spaces, normalized to uppercase.
     */
    public static function hashRecoveryCode(string $code): string
    {
        $clean = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code));
        return hash('sha256', $clean);
    }

    /**
     * Hash an array of recovery codes for persistent storage.
     */
    public static function hashRecoveryCodes(array $codes): array
    {
        return array_map(fn($code) => self::hashRecoveryCode($code), $codes);
    }

    /**
     * Verify and consume a recovery code from the array of hashed codes.
     *
     * @param array $hashedCodes Array of hashed recovery codes
     * @param string $providedCode User input code
     * @return array|null Returns updated array of remaining hashed codes, or null if invalid
     */
    public static function verifyAndConsumeRecoveryCode(array $hashedCodes, string $providedCode): ?array
    {
        $normalizedHash = self::hashRecoveryCode($providedCode);

        $foundIndex = array_search($normalizedHash, $hashedCodes, true);
        if ($foundIndex === false) {
            return null;
        }

        unset($hashedCodes[$foundIndex]);

        return array_values($hashedCodes);
    }
}
