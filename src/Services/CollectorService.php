<?php

declare(strict_types=1);

namespace Babod\NetManager\Services;

use Babod\NetManager\Repository\SwitchRepository;

final class CollectorService
{
    public function __construct(private readonly SwitchRepository $switches)
    {
    }

    public function collect(int $switchId): array
    {
        $switch = $this->switches->find($switchId);
        if ($switch === null) {
            throw new \RuntimeException('Switch nem található.');
        }

        $client = $this->switches->clientFor($switch);
        $snapshot = $client->collectSnapshot();

        $collectedAt = (string) ($snapshot['collected_at'] ?? date('c'));
        $this->switches->storePortStats($switchId, $snapshot['ports'] ?? [], $collectedAt);
        $this->switches->updateStatus($switchId, 'online', $collectedAt);

        return $snapshot;
    }

    public function collectAll(): array
    {
        $results = [];
        foreach ($this->switches->all() as $switch) {
            try {
                $results[$switch['id']] = [
                    'ok' => true,
                    'data' => $this->collect((int) $switch['id']),
                ];
            } catch (\Throwable $e) {
                $this->switches->updateStatus((int) $switch['id'], 'offline');
                $results[$switch['id']] = [
                    'ok' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }
}
