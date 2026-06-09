<?php

declare(strict_types=1);

namespace Babod\NetManager\Zyxel;

final class ApiFactory
{
    public static function detect(string $host, string $password, bool $preferHttps = true): string
    {
        $schemes = $preferHttps ? ['https', 'http'] : ['http', 'https'];

        foreach ($schemes as $scheme) {
            $http = new HttpSession($scheme . '://' . $host, $scheme === 'https');
            $query = ['cmd' => 'home_loginInfo', 'dummy' => time()];
            $query['bj4'] = md5(http_build_query($query));
            $response = $http->get('cgi/get.cgi', $query);

            if ($response['status'] === 200) {
                $json = json_decode($response['body'], true);
                if (is_array($json) && isset($json['data']['modulus'])) {
                    return 'modern';
                }
            }
        }

        foreach ($schemes as $scheme) {
            $http = new HttpSession($scheme . '://' . $host, $scheme === 'https');
            $response = $http->get('system_data.js');
            if ($response['status'] === 200 && str_contains($response['body'], 'model_name')) {
                return 'legacy';
            }
        }

        throw new \RuntimeException('Nem sikerült felismerni a switch API típusát. Ellenőrizd az IP-t és a hálózati elérést.');
    }

    public static function create(string $host, string $password, ?string $apiType = null, bool $useHttps = true): ZyxelClientInterface
    {
        $apiType ??= self::detect($host, $password, $useHttps);

        return match ($apiType) {
            'modern' => new ModernClient($host, $password, $useHttps),
            default => new LegacyClient($host, $password, $useHttps),
        };
    }
}
