<?php

declare(strict_types=1);

namespace Babod\NetManager;

use PDO;

final class Database
{
    private PDO $pdo;

    public function __construct(string $databasePath)
    {
        $this->pdo = new PDO('sqlite:' . $databasePath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->migrate();
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    private function migrate(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS switches (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                host TEXT NOT NULL UNIQUE,
                password_enc TEXT NOT NULL,
                api_type TEXT,
                use_https INTEGER NOT NULL DEFAULT 1,
                location TEXT,
                notes TEXT,
                last_seen_at TEXT,
                last_status TEXT,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )'
        );

        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS port_stats (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                switch_id INTEGER NOT NULL,
                collected_at TEXT NOT NULL,
                port_index INTEGER NOT NULL,
                status TEXT,
                speed TEXT,
                rx_packets INTEGER NOT NULL DEFAULT 0,
                tx_packets INTEGER NOT NULL DEFAULT 0,
                FOREIGN KEY (switch_id) REFERENCES switches(id) ON DELETE CASCADE
            )'
        );

        $this->pdo->exec(
            'CREATE INDEX IF NOT EXISTS idx_port_stats_switch_time
             ON port_stats (switch_id, collected_at DESC)'
        );
    }
}
