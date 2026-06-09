<?php

declare(strict_types=1);

namespace Babod\NetManager\Zyxel;

final class ModernClient implements ZyxelClientInterface
{
    private HttpSession $http;
    private bool $loggedIn = false;

    public function __construct(
        private readonly string $host,
        private readonly string $password,
        private readonly bool $useHttps = true,
    ) {
        $scheme = $useHttps ? 'https' : 'http';
        $this->http = new HttpSession($scheme . '://' . $host, $useHttps);
    }

    public function getApiType(): string
    {
        return 'modern';
    }

    public function testConnection(): array
    {
        $this->login();
        $info = $this->fetchSystemInfo();
        $this->logout();

        return $info;
    }

    public function collectSnapshot(): array
    {
        $this->login();

        try {
            $system = $this->fetchSystemInfo();
            $portCount = (int) ($system['port_count'] ?? 8);
            $linkData = $this->getCommand('home_linkData');
            $vlanData = $this->fetchVlanInfo();

            $ports = $this->parseModernPorts($linkData, $portCount);
            $vlan = $this->parseModernVlan($vlanData, $portCount, $portCount);

            return [
                'system' => $system,
                'ports' => $ports,
                'vlan' => $vlan,
                'collected_at' => date('c'),
            ];
        } finally {
            $this->logout();
        }
    }

    public function saveVlanConfig(array $vlanConfig): void
    {
        $this->login();

        try {
            $portCount = (int) ($vlanConfig['port_count'] ?? 8);
            $pvids = [];
            for ($i = 0; $i < $portCount; $i++) {
                $pvids[] = (int) ($vlanConfig['pvids'][$i + 1] ?? 1);
            }

            $vlanRows = [];
            foreach ($vlanConfig['vlans'] as $vlan) {
                [$memberMask, $tagMask] = JsDataParser::buildVlanMasks($vlan['ports'] ?? [], $portCount);
                $vlanRows[] = [
                    'vlanId' => (int) $vlan['id'],
                    'member' => $memberMask,
                    'tag' => $tagMask,
                ];
            }

            $payload = [
                '_ds=1&pvid=' . implode(',', $pvids) . '&vlan=' . rawurlencode(json_encode($vlanRows)) . '&xsrfToken=fa9358fbd291c3bd&_de=1' => new \stdClass(),
            ];

            $lastError = 'ismeretlen hiba';
            foreach (['vlan_8021qSet', 'vlan_dot1qSet', 'vlan_vlanSet'] as $cmd) {
                try {
                    $result = $this->setCommand($cmd, $payload);
                    if (($result['status'] ?? '') === 'ok') {
                        return;
                    }
                    $lastError = (string) ($result['message'] ?? $result['status'] ?? $lastError);
                } catch (\Throwable $e) {
                    $lastError = $e->getMessage();
                }
            }
            throw new \RuntimeException('VLAN mentése sikertelen: ' . $lastError);
        } finally {
            $this->logout();
        }
    }

    private function login(): void
    {
        if ($this->loggedIn) {
            return;
        }

        $loginInfo = $this->getCommand('home_loginInfo', false);
        $modulus = (string) ($loginInfo['modulus'] ?? '');
        if ($modulus === '') {
            throw new \RuntimeException('Modern API login info hiányzik.');
        }

        $encrypted = ZyxelCrypt::encryptModernPassword($this->password, $modulus);
        $auth = $this->setCommand('home_loginAuth', [
            '_ds=1&password=' . $encrypted . '&xsrfToken=fa9358fbd291c3bd&_de=1' => new \stdClass(),
        ], false);

        $authId = (string) ($auth['authId'] ?? '');
        $status = $this->setCommand('home_loginStatus', [
            '_ds=1&authId=' . $authId . '&xsrfToken=fa9358fbd291c3bd&_de=1' => new \stdClass(),
        ], false);

        if (($status['status'] ?? '') !== 'ok') {
            throw new \RuntimeException('Bejelentkezés sikertelen: ' . ($status['status'] ?? 'ismeretlen'));
        }

        $this->loggedIn = true;
    }

    private function logout(): void
    {
        if (!$this->loggedIn) {
            return;
        }

        try {
            $this->setCommand('home_logout', ['_ds=1&_de=1' => new \stdClass()]);
        } catch (\Throwable) {
            // ignore logout errors
        }

        $this->loggedIn = false;
    }

    private function fetchSystemInfo(): array
    {
        $main = $this->getCommand('home_main');
        $system = $this->getCommand('home_systemData');

        return [
            'model' => (string) ($main['model_name'] ?? 'Zyxel Switch'),
            'hostname' => (string) ($main['sys_dev_name'] ?? $system['sys_dev_name'] ?? ''),
            'firmware' => (string) ($main['sys_fmw_ver'] ?? ''),
            'ip' => (string) ($main['sys_IP'] ?? $this->host),
            'mac' => (string) ($main['sys_MAC'] ?? ''),
            'gateway' => (string) ($main['sys_gateway'] ?? ''),
            'subnet' => (string) ($main['sys_sbnt_msk'] ?? ''),
            'uptime' => '',
            'port_count' => (int) ($main['Max_port'] ?? $main['max_port'] ?? 8),
            'api_type' => 'modern',
        ];
    }

    private function getCommand(string $cmd, bool $autoLogin = true): array
    {
        if ($autoLogin) {
            $this->login();
        }

        $response = $this->http->get('cgi/get.cgi', $this->signedQuery(['cmd' => $cmd]));
        if ($response['status'] !== 200) {
            throw new \RuntimeException('API hiba (' . $cmd . '): HTTP ' . $response['status']);
        }

        $json = $this->decodeJson($response['body']);
        if (isset($json['logout'])) {
            $this->loggedIn = false;
            $this->login();
            return $this->getCommand($cmd);
        }

        return is_array($json['data'] ?? null) ? $json['data'] : [];
    }

    private function setCommand(string $cmd, array $payload, bool $autoLogin = true): array
    {
        if ($autoLogin) {
            $this->login();
        }

        $response = $this->http->postJson('cgi/set.cgi', $payload, $this->signedQuery(['cmd' => $cmd]));
        if ($response['status'] !== 200) {
            throw new \RuntimeException('API set hiba (' . $cmd . '): HTTP ' . $response['status']);
        }

        $json = $this->decodeJson($response['body']);

        return is_array($json['data'] ?? null) ? $json['data'] : [];
    }

  /**
     * @param array<string, mixed> $params
     * @return array<string, string|int>
     */
    private function signedQuery(array $params): array
    {
        $params['dummy'] = time();
        $query = http_build_query($params);
        $params['bj4'] = md5($query);

        return $params;
    }

    private function decodeJson(string $body): array
    {
        $json = json_decode($body, true);
        if (!is_array($json)) {
            throw new \RuntimeException('Érvénytelen JSON válasz a switchtől.');
        }

        return $json;
    }

    private function parseModernPorts(array $linkData, int $portCount): array
    {
        $ports = [];
        $statuses = $linkData['portstatus'] ?? [];
        $speeds = $linkData['speed'] ?? [];
        $stats = $linkData['Stats'] ?? [];

        for ($i = 0; $i < $portCount; $i++) {
            $row = is_array($stats[$i] ?? null) ? $stats[$i] : [];
            $ports[] = [
                'index' => $i + 1,
                'status' => (string) ($statuses[$i] ?? 'Down'),
                'speed' => (string) ($speeds[$i] ?? 'auto'),
                'loop' => 'Normal',
                'rx_packets' => (int) (($row[0] ?? 0)),
                'tx_packets' => (int) (($row[1] ?? 0)),
            ];
        }

        return $ports;
    }

    private function fetchVlanInfo(): array
    {
        foreach (['vlan_8021qInfo', 'vlan_dot1qInfo', 'vlan_vlanInfo', 'vlan_8021QInfo'] as $cmd) {
            try {
                $data = $this->getCommand($cmd);
                if ($data !== []) {
                    return $data;
                }
            } catch (\Throwable) {
                // try next command alias
            }
        }

        return [];
    }

    private function parseModernVlan(array $vlanData, int $portCount, int $fallbackPortCount): array
    {
        if ($vlanData === []) {
            return JsDataParser::parseVlanConfig(['pvids' => array_fill(0, $fallbackPortCount, 1), 'qvlans' => [['1', '0xFF', '0x0']]], $fallbackPortCount);
        }

        $pvids = [];
        $rawPvids = $vlanData['pvid'] ?? $vlanData['pvids'] ?? [];
        if (is_string($rawPvids)) {
            $rawPvids = array_map('intval', explode(',', $rawPvids));
        }
        for ($i = 0; $i < $portCount; $i++) {
            $pvids[$i + 1] = (int) ($rawPvids[$i] ?? 1);
        }

        $vlans = [];
        $rows = $vlanData['vlan'] ?? $vlanData['qvlans'] ?? [];
        foreach ($rows as $row) {
            if (is_array($row) && isset($row['vlanId'])) {
                $vlanId = (int) $row['vlanId'];
                $memberMask = (int) ($row['member'] ?? 0);
                $tagMask = (int) ($row['tag'] ?? 0);
            } else {
                $vlanId = (int) ($row[0] ?? 0);
                $memberMask = is_string($row[1] ?? 0) ? hexdec(str_replace('0x', '', $row[1])) : (int) ($row[1] ?? 0);
                $tagMask = is_string($row[2] ?? 0) ? hexdec(str_replace('0x', '', $row[2])) : (int) ($row[2] ?? 0);
            }

            $ports = [];
            for ($i = 0; $i < $portCount; $i++) {
                if (($memberMask & (1 << $i)) === 0) {
                    $ports[$i + 1] = 'none';
                } else {
                    $ports[$i + 1] = ($tagMask & (1 << $i)) ? 'tagged' : 'untagged';
                }
            }

            $vlans[] = [
                'id' => $vlanId,
                'member_mask' => $memberMask,
                'tag_mask' => $tagMask,
                'ports' => $ports,
            ];
        }

        return [
            'vlans' => $vlans,
            'pvids' => $pvids,
            'max_vlans' => (int) ($vlanData['max_vlan'] ?? 32),
            'port_count' => $portCount,
        ];
    }
}
