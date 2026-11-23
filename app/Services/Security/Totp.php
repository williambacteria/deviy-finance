<?php

namespace App\Services\Security;

class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function generateSecret(int $bytes = 20): string
    {
        return $this->base32Encode(random_bytes($bytes));
    }

    public function code(string $secret, int $digits = 6, ?int $timestamp = null): string
    {
        $counter = intdiv($timestamp ?? time(), 30);
        $key = $this->base32Decode($secret);
        $hash = hash_hmac('sha1', pack('N2', 0, $counter), $key, true);
        $offset = ord($hash[19]) & 0x0F;
        $binary = substr($hash, $offset, 4);
        $value = unpack('N', $binary)[1] & 0x7FFFFFFF;
        $modulo = 10 ** $digits;

        return str_pad((string) ($value % $modulo), $digits, '0', STR_PAD_LEFT);
    }

    public function verify(string $secret, string $code, int $window = 1, int $digits = 6, ?int $timestamp = null): bool
    {
        $code = preg_replace('/\D/', '', $code) ?? '';
        if (strlen($code) !== $digits) {
            return false;
        }

        $now = $timestamp ?? time();
        for ($offset = -$window; $offset <= $window; $offset++) {
            if (hash_equals($this->code($secret, $digits, $now + ($offset * 30)), $code)) {
                return true;
            }
        }

        return false;
    }

    public function provisioningUri(string $account, string $secret, string $issuer = 'Deviy Cloud'): string
    {
        $label = rawurlencode($issuer.':'.$account);

        return 'otpauth://totp/'.$label.'?'.http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => 6,
            'period' => 30,
        ]);
    }

    private function base32Encode(string $bytes): string
    {
        $bits = '';
        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        $output = '';
        foreach (str_split($bits, 5) as $chunk) {
            $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            $output .= self::ALPHABET[bindec($chunk)];
        }

        return $output;
    }

    private function base32Decode(string $secret): string
    {
        $secret = strtoupper(preg_replace('/[^A-Z2-7]/i', '', $secret) ?? '');
        $bits = '';
        foreach (str_split($secret) as $character) {
            $position = strpos(self::ALPHABET, $character);
            if ($position === false) {
                continue;
            }
            $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
        }

        $output = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $output .= chr(bindec($chunk));
            }
        }

        return $output;
    }
}
