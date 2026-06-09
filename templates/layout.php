<?php /** @var string $content */ ?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Babod NetManager') ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<?php if (!empty($_SESSION['authenticated'])): ?>
<header class="topbar">
    <div class="brand"><?= htmlspecialchars($config['app_name'] ?? 'Babod NetManager') ?></div>
    <nav>
        <a href="/">Áttekintés</a>
        <a href="/switches">Switchek</a>
        <a href="/vlan">VLAN</a>
        <a href="/stats">Statisztika</a>
        <a href="/logout">Kijelentkezés</a>
    </nav>
</header>
<?php endif; ?>

<main class="container">
    <?php foreach (($flash ?? []) as $message): ?>
        <div class="alert alert-<?= htmlspecialchars($message['type']) ?>">
            <?= htmlspecialchars($message['message']) ?>
        </div>
    <?php endforeach; ?>
    <?= $content ?>
</main>

<script src="/assets/js/app.js"></script>
</body>
</html>
