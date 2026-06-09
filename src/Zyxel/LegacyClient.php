<?php

declare(strict_types=1);

namespace Babod\NetManager\Zyxel;

final class LegacyClient implements ZyxelClientInterface
{
    private HttpSession $http;

    public function __construct(
        private readonly string $host,
        private readonly string $password,
        private readonly bool $useHttps = false,
    ) {
        $scheme = $useHttps ? 'https' : 'http';
        $this->http = new HttpSession($scheme . '://' . $host, $useHttps);
    }

    public function getApiType(): string
    {
        return 'legacy';
    }

    public function testConnection(): array
    {
        $this->login();
        $info = $this->fetchSystemData();
        $this->logout();

        return $info;
    }

    public function collectSnapshot(): array
    {
        $this->login();

        try {
            $system = $this->fetchSystemData();
            $portCount = (int) ($system['port_count'] ?? 8);

            $linkJs = $this->fetchJsFile('link_data.js');
            $vlanJs = $this->fetchJsFile('VLAN_1Q_List_data.js');

            $linkData = JsDataParser::parse($linkJs);
            $vlanData = JsDataParser::parse($vlanJs);

            return [
                'system' => $system,
                'ports' => JsDataParser::parsePortStats($linkData, $portCount),
                'vlan' => JsDataParser::parseVlanConfig($vlanData, $portCount),
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
            $pvids = $vlanConfig['pvids'] ?? [];
            $pvidFields = [];
            for ($i = 0; $i < $portCount; $i++) {
                $pvidFields['g_pvid' . $i] = (int) ($pvids[$i + 1] ?? 1);
            }

            $pvidResponse = $this->http->postForm('vlan_pvid_set.cgi', $pvidFields);
            if ($pvidResponse['status'] !== 200) {
                throw new \RuntimeException('PVID mentése sikertelen (HTTP ' . $pvidResponse['status'] . ').');
            }

            $vlanFields = [
                'g_vlan_cnt' => count($vlanConfig['vlans'] ?? []),
            ];

            foreach ($vlanConfig['vlans'] as $index => $vlan) {
                [$memberMask, $tagMask] = JsDataParser::buildVlanMasks($vlan['ports'] ?? [], $portCount);
                $vlanFields['g_vlan_id' . $index] = (int) $vlan['id'];
                $vlanFields['g_vlan_mem' . $index] = $memberMask;
                $vlanFields['g_vlan_tag' . $index] = $tagMask;
            }

            $vlanResponse = $this->http->postForm('vlan_1q_set.cgi', $vlanFields);
            if ($vlanResponse['status'] !== 200) {
                throw new \RuntimeException('VLAN mentése sikertelen (HTTP ' . $vlanResponse['status'] . ').');
            }
        } finally {
            $this->logout();
        }
    }

    private function login(): void
    {
        $response = $this->http->postForm('login.cgi', [
            'password' => ZyxelCrypt::encryptLegacyPassword($this->password),
        ]);

        if ($response['status'] !== 200) {
            throw new \RuntimeException('Bejelentkezés sikertelen (HTTP ' . $response['status'] . ').');
        }

        if (str_contains($response['body'], 'Incorrect password')) {
            throw new \RuntimeException('Hibás switch jelszó.');
        }

        if (str_contains($response['body'], 'logged in already')) {
            throw new \RuntimeException('A switch már használatban van (másik aktív munkamenet).');
        }
    }

    private function logout(): void
    {
        $this->http->get('logout.html');
    }

    private function fetchJsFile(string $filename): string
    {
        $response = $this->http->get($filename);
        if ($response['status'] !== 200 || trim($response['body']) === '') {
            throw new \RuntimeException('Nem érhető el: ' . $filename);
        }

        return $response['body'];
    }

    private function fetchSystemData(): array
    {
        $response = $this->http->get('system_data.js');
        if ($response['status'] !== 200) {
            throw new \RuntimeException('system_data.js nem olvasható.');
        }

        $data = JsDataParser::parse($response['body']);

        return [
            'model' => (string) ($data['model_name'] ?? 'GS1200'),
            'hostname' => (string) ($data['sys_dev_name'] ?? ''),
            'firmware' => (string) ($data['sys_fmw_ver'] ?? ''),
            'ip' => (string) ($data['sys_IP'] ?? $this->host),
            'mac' => (string) ($data['sys_MAC'] ?? ''),
            'gateway' => (string) ($data['sys_gateway'] ?? ''),
            'subnet' => (string) ($data['sys_sbnt_msk'] ?? ''),
            'uptime' => (string) ($data['sys_uptime'] ?? ''),
            'port_count' => (int) ($data['Max_port'] ?? $data['max_port'] ?? 8),
            'api_type' => 'legacy',
        ];
    }
}
