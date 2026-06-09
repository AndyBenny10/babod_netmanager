<?php

declare(strict_types=1);

namespace Babod\NetManager\Repository;

use Babod\NetManager\Database;
use Babod\NetManager\Support\Crypto;
use Babod\NetManager\Zyxel\ApiFactory;
use Babod\NetManager\Zyxel\ZyxelClientInterface;
use PDO;

final class SwitchRepository
{
    public function __construct(
        private readonly Database $database,
        private readonly string $encryptionKey,
    ) {
    }

  /** @return list<array<string, mixed>> */
    public function all(): array
    {
        $stmt = $this->database->pdo()->query('SELECT * FROM switches ORDER BY name ASC');

        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->database->pdo()->prepare('SELECT * FROM switches WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function create(array $input): int
    {
        $now = date('c');
        $apiType = $input['api_type'] ?: ApiFactory::detect(
            $input['host'],
            $input['password'],
            (bool) $input['use_https']
        );

        $stmt = $this->database->pdo()->prepare(
            'INSERT INTO switches (name, host, password_enc, api_type, use_https, location, notes, created_at, updated_at)
             VALUES (:name, :host, :password_enc, :api_type, :use_https, :location, :notes, :created_at, :updated_at)'
        );
        $stmt->execute([
            'name' => trim($input['name']),
            'host' => trim($input['host']),
            'password_enc' => Crypto::encrypt($input['password'], $this->encryptionKey),
            'api_type' => $apiType,
            'use_https' => !empty($input['use_https']) ? 1 : 0,
            'location' => trim((string) ($input['location'] ?? '')),
            'notes' => trim((string) ($input['notes'] ?? '')),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $this->database->pdo()->lastInsertId();
    }

    public function update(int $id, array $input): void
    {
        $existing = $this->find($id);
        if ($existing === null) {
            throw new \RuntimeException('Switch nem található.');
        }

        $password = trim((string) ($input['password'] ?? ''));
        $passwordEnc = $password !== ''
            ? Crypto::encrypt($password, $this->encryptionKey)
            : $existing['password_enc'];

        $apiType = trim((string) ($input['api_type'] ?? ''));
        if ($apiType === '' && $password !== '') {
            $apiType = ApiFactory::detect($input['host'], $password, !empty($input['use_https']));
        } elseif ($apiType === '') {
            $apiType = (string) $existing['api_type'];
        }

        $stmt = $this->database->pdo()->prepare(
            'UPDATE switches
             SET name = :name, host = :host, password_enc = :password_enc, api_type = :api_type,
                 use_https = :use_https, location = :location, notes = :notes, updated_at = :updated_at
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'name' => trim($input['name']),
            'host' => trim($input['host']),
            'password_enc' => $passwordEnc,
            'api_type' => $apiType,
            'use_https' => !empty($input['use_https']) ? 1 : 0,
            'location' => trim((string) ($input['location'] ?? '')),
            'notes' => trim((string) ($input['notes'] ?? '')),
            'updated_at' => date('c'),
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->database->pdo()->prepare('DELETE FROM switches WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function clientFor(array $switch): ZyxelClientInterface
    {
        $password = Crypto::decrypt($switch['password_enc'], $this->encryptionKey);

        return ApiFactory::create(
            $switch['host'],
            $password,
            $switch['api_type'] ?: null,
            (bool) $switch['use_https']
        );
    }

    public function updateStatus(int $id, string $status, ?string $seenAt = null): void
    {
        $stmt = $this->database->pdo()->prepare(
            'UPDATE switches SET last_status = :status, last_seen_at = :seen_at, updated_at = :updated_at WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'status' => $status,
            'seen_at' => $seenAt ?? date('c'),
            'updated_at' => date('c'),
        ]);
    }

    public function storePortStats(int $switchId, array $ports, string $collectedAt): void
    {
        $pdo = $this->database->pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO port_stats (switch_id, collected_at, port_index, status, speed, rx_packets, tx_packets)
             VALUES (:switch_id, :collected_at, :port_index, :status, :speed, :rx_packets, :tx_packets)'
        );

        foreach ($ports as $port) {
            $stmt->execute([
                'switch_id' => $switchId,
                'collected_at' => $collectedAt,
                'port_index' => (int) $port['index'],
                'status' => (string) $port['status'],
                'speed' => (string) $port['speed'],
                'rx_packets' => (int) $port['rx_packets'],
                'tx_packets' => (int) $port['tx_packets'],
            ]);
        }
    }

  /** @return list<array<string, mixed>> */
    public function latestPortStats(int $switchId): array
    {
        $pdo = $this->database->pdo();
        $timeStmt = $pdo->prepare(
            'SELECT collected_at FROM port_stats WHERE switch_id = :switch_id ORDER BY collected_at DESC LIMIT 1'
        );
        $timeStmt->execute(['switch_id' => $switchId]);
        $latest = $timeStmt->fetchColumn();
        if ($latest === false) {
            return [];
        }

        $stmt = $pdo->prepare(
            'SELECT * FROM port_stats WHERE switch_id = :switch_id AND collected_at = :collected_at ORDER BY port_index ASC'
        );
        $stmt->execute(['switch_id' => $switchId, 'collected_at' => $latest]);

        return $stmt->fetchAll();
    }

  /** @return list<array<string, mixed>> */
    public function portHistory(int $switchId, int $portIndex, int $limit = 48): array
    {
        $stmt = $this->database->pdo()->prepare(
            'SELECT collected_at, rx_packets, tx_packets, status, speed
             FROM port_stats
             WHERE switch_id = :switch_id AND port_index = :port_index
             ORDER BY collected_at DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':switch_id', $switchId, PDO::PARAM_INT);
        $stmt->bindValue(':port_index', $portIndex, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return array_reverse($stmt->fetchAll());
    }

    public function dashboardSummary(): array
    {
        $switches = $this->all();
        $online = 0;
        foreach ($switches as $switch) {
            if (($switch['last_status'] ?? '') === 'online') {
                $online++;
            }
        }

        return [
            'total' => count($switches),
            'online' => $online,
            'offline' => count($switches) - $online,
        ];
    }
}
