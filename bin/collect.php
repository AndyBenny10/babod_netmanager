#!/usr/bin/env php
<?php

declare(strict_types=1);

use Babod\NetManager\Database;
use Babod\NetManager\Repository\SwitchRepository;
use Babod\NetManager\Services\CollectorService;

$config = require dirname(__DIR__) . '/src/bootstrap.php';

$database = new Database($config['data_dir'] . '/netmanager.sqlite');
$switchRepo = new SwitchRepository($database, (string) $config['encryption_key']);
$collector = new CollectorService($switchRepo);

$results = $collector->collectAll();

foreach ($results as $switchId => $result) {
    if ($result['ok']) {
        echo "[OK] Switch #{$switchId}\n";
        continue;
    }
    echo "[ERR] Switch #{$switchId}: {$result['error']}\n";
}
