<?php

declare(strict_types=1);

$configFile = dirname(__DIR__) . '/config.php';
if (!is_file($configFile)) {
    $configFile = dirname(__DIR__) . '/config.example.php';
}

/** @var array<string, mixed> $config */
$config = require $configFile;

date_default_timezone_set((string) $config['timezone']);

if (!is_dir($config['data_dir'])) {
    mkdir($config['data_dir'], 0750, true);
}

session_name((string) $config['session_name']);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'Babod\\NetManager\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $path = dirname(__DIR__) . '/src/' . $relative . '.php';
    if (is_file($path)) {
        require $path;
    }
});

return $config;
