<?php

declare(strict_types=1);

namespace Babod\NetManager\Zyxel;

final class JsDataParser
{
    public static function parse(string $javascript): array
    {
        $data = [];

        if (preg_match_all('/var\s+([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(\[[\s\S]*?\]);/m', $javascript, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $data[$match[1]] = self::decodeJsValue($match[2]);
            }
        }

        if (preg_match_all('/var\s+([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(\'[^\']*\'|"[^"]*"|\d+|0x[0-9A-Fa-f]+);/m', $javascript, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                if (!array_key_exists($match[1], $data)) {
                    $data[$match[1]] = self::decodeJsValue($match[2]);
                }
            }
        }

        return $data;
    }

    private static function decodeJsValue(string $value): mixed
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if ($value[0] === '[') {
            return self::parseArray($value);
        }

        if (($value[0] === "'" || $value[0] === '"') && substr($value, -1) === $value[0]) {
            return stripcslashes(substr($value, 1, -1));
        }

        if (str_starts_with(strtolower($value), '0x')) {
            return hexdec(substr($value, 2));
        }

        if (is_numeric($value)) {
            return str_contains($value, '.') ? (float) $value : (int) $value;
        }

        return $value;
    }

    private static function parseArray(string $source): array
    {
        $json = preg_replace_callback(
            "/'([^'\\\\]*(?:\\\\.[^'\\\\]*)*)'/",
            static fn (array $m): string => '"' . addcslashes(stripslashes($m[1]), "\"\\") . '"',
            $source
        );

        $json = preg_replace('/,\s*]/', ']', (string) $json);
        $decoded = json_decode((string) $json, true);

        return is_array($decoded) ? $decoded : [];
    }

    public static function parseVlanConfig(array $jsData, int $portCount): array
    {
        $pvids = $jsData['pvids'] ?? [];
        $qvlans = $jsData['qvlans'] ?? [];

        $vlans = [];
        foreach ($qvlans as $entry) {
            if (!is_array($entry) || count($entry) < 3) {
                continue;
            }

            $vlanId = (int) $entry[0];
            $memberMask = self::hexToInt((string) $entry[1]);
            $tagMask = self::hexToInt((string) $entry[2]);

            $ports = [];
            for ($i = 0; $i < $portCount; $i++) {
                if (($memberMask & (1 << $i)) === 0) {
                    $ports[$i + 1] = 'none';
                    continue;
                }
                $ports[$i + 1] = ($tagMask & (1 << $i)) ? 'tagged' : 'untagged';
            }

            $vlans[] = [
                'id' => $vlanId,
                'member_mask' => $memberMask,
                'tag_mask' => $tagMask,
                'ports' => $ports,
            ];
        }

        $portPvids = [];
        for ($i = 0; $i < $portCount; $i++) {
            $portPvids[$i + 1] = isset($pvids[$i]) ? (int) $pvids[$i] : 1;
        }

        return [
            'vlans' => $vlans,
            'pvids' => $portPvids,
            'max_vlans' => (int) ($jsData['max_vlan'] ?? 32),
            'port_count' => $portCount,
        ];
    }

    public static function parsePortStats(array $jsData, int $portCount): array
    {
        $portstatus = $jsData['portstatus'] ?? [];
        $speed = $jsData['speed'] ?? [];
        $stats = $jsData['Stats'] ?? [];
        $loopStatus = $jsData['loop_status'] ?? [];

        $ports = [];
        for ($i = 0; $i < $portCount; $i++) {
            $row = is_array($stats[$i] ?? null) ? $stats[$i] : [];
            $tx = self::sumStatsRow($row, [1, 2, 3]);
            $rx = self::sumStatsRow($row, [6, 7, 8, 10]);

            $ports[] = [
                'index' => $i + 1,
                'status' => (string) ($portstatus[$i] ?? 'Down'),
                'speed' => (string) ($speed[$i] ?? '0 Mbps'),
                'loop' => (string) ($loopStatus[$i] ?? 'Normal'),
                'rx_packets' => $rx,
                'tx_packets' => $tx,
            ];
        }

        return $ports;
    }

    private static function sumStatsRow(array $row, array $indexes): int
    {
        $sum = 0;
        foreach ($indexes as $index) {
            $sum += (int) ($row[$index] ?? 0);
        }

        return $sum;
    }

    private static function hexToInt(string $value): int
    {
        return (int) hexdec(str_replace('0x', '', $value));
    }

    public static function buildVlanMasks(array $ports, int $portCount): array
    {
        $memberMask = 0;
        $tagMask = 0;

        for ($i = 1; $i <= $portCount; $i++) {
            $mode = $ports[$i] ?? 'none';
            if ($mode === 'none') {
                continue;
            }
            $bit = 1 << ($i - 1);
            $memberMask |= $bit;
            if ($mode === 'tagged') {
                $tagMask |= $bit;
            }
        }

        return [$memberMask, $tagMask];
    }

    public static function maskToHex(int $mask): string
    {
        return '0x' . strtoupper(dechex($mask));
    }
}
