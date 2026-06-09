<?php

declare(strict_types=1);

namespace Babod\NetManager\Zyxel;

final class ZyxelCrypt
{
    private const CHARS = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

    public static function encryptLegacyPassword(string $password): string
    {
        $length = strlen($password);
        $result = '';

        for ($i = 0; $i < $length; $i++) {
            $result .= self::CHARS[random_int(0, strlen(self::CHARS) - 1)];
            $result .= chr(ord($password[$i]) - $length);
        }

        return $result . self::CHARS[random_int(0, strlen(self::CHARS) - 1)];
    }

    public static function encryptModernPassword(string $password, string $modulusHex): string
    {
        $der = self::buildPublicKeyDer($modulusHex);
        $pem = "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split(base64_encode($der), 64, "\n")
            . "-----END PUBLIC KEY-----\n";

        $key = openssl_pkey_get_public($pem);
        if ($key === false) {
            throw new \RuntimeException('Nem sikerült RSA kulcsot létrehozni a switch modulusból.');
        }

        $encrypted = '';
        if (!openssl_public_encrypt($password, $encrypted, $key, OPENSSL_PKCS1_PADDING)) {
            throw new \RuntimeException('Jelszó titkosítása sikertelen.');
        }

        return str_replace(['+', '=', "\n"], ['%2B', '%3D', ''], base64_encode($encrypted));
    }

    private static function buildPublicKeyDer(string $modulusHex): string
    {
        $modulus = ltrim($modulusHex, '0');
        if ($modulus === '') {
            $modulus = '00';
        }
        $modulusBin = hex2bin(strlen($modulus) % 2 === 0 ? $modulus : '0' . $modulus);
        $exponentBin = hex2bin('010001');

        $modulusDer = self::encodeInteger($modulusBin ?: '');
        $exponentDer = self::encodeInteger($exponentBin ?: '');
        $rsaPublicKey = self::encodeSequence($modulusDer . $exponentDer);

        $oid = hex2bin('2a864886f70d010101');
        $null = hex2bin('0500');
        $algorithmId = self::encodeSequence($oid . $null);
        $bitString = chr(0x00) . $rsaPublicKey;

        return self::encodeSequence(
            $algorithmId . self::encodeTag(0x03, $bitString)
        );
    }

    private static function encodeInteger(string $value): string
    {
        if ($value !== '' && (ord($value[0]) & 0x80)) {
            $value = "\x00" . $value;
        }

        return self::encodeTag(0x02, $value);
    }

    private static function encodeSequence(string $value): string
    {
        return self::encodeTag(0x30, $value);
    }

    private static function encodeTag(int $tag, string $value): string
    {
        return chr($tag) . self::encodeLength(strlen($value)) . $value;
    }

    private static function encodeLength(int $length): string
    {
        if ($length < 0x80) {
            return chr($length);
        }

        $bytes = '';
        while ($length > 0) {
            $bytes = chr($length & 0xff) . $bytes;
            $length >>= 8;
        }

        return chr(0x80 | strlen($bytes)) . $bytes;
    }
}
