<?php

declare(strict_types=1);

return [
    'app_name' => 'Babod NetManager',
    'timezone' => 'Europe/Budapest',
    'data_dir' => __DIR__ . '/data',
    'session_name' => 'babod_netmanager',
    // Egyszerű belső jelszó a webes felülethez (változtasd meg!)
    'admin_password' => 'admin123',
    // Switch jelszavak titkosításához (32+ karakter ajánlott)
    'encryption_key' => 'change-me-to-a-long-random-secret-key!!',
];
