<?php

declare(strict_types=1);

namespace Babod\NetManager\Support;

final class Crypto
{
    public static function encrypt(string $plain, string $key): string
    {
        $iv = random_bytes(16);
        $cipher = openssl_encrypt($plain, 'AES-256-CBC', hash('sha256', $key, true), OPENSSL_RAW_DATA, $iv);
        if ($cipher === false) {
            throw new \RuntimeException('Titkosítás sikertelen.');
        }

        return base64_encode($iv . $cipher);
    }

    public static function decrypt(string $encoded, string $key): string
    {
        $raw = base64_decode($encoded, true);
        if ($raw === false || strlen($raw) < 17) {
            throw new \RuntimeException('Érvénytelen titkosított adat.');
        }

        $iv = substr($raw, 0, 16);
        $cipher = substr($raw, 16);
        $plain = openssl_decrypt($cipher, 'AES-256-CBC', hash('sha256', $key, true), OPENSSL_RAW_DATA, $iv);
        if ($plain === false) {
            throw new \RuntimeException('Visszafejtés sikertelen.');
        }

        return $plain;
    }
}
