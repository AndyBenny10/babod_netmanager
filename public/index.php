<?php

declare(strict_types=1);

use Babod\NetManager\Database;
use Babod\NetManager\Http\Auth;
use Babod\NetManager\Http\Router;
use Babod\NetManager\Http\View;
use Babod\NetManager\Repository\SwitchRepository;
use Babod\NetManager\Services\CollectorService;
$config = require dirname(__DIR__) . '/src/bootstrap.php';

$database = new Database($config['data_dir'] . '/netmanager.sqlite');
$switchRepo = new SwitchRepository($database, (string) $config['encryption_key']);
$collector = new CollectorService($switchRepo);
$auth = new Auth((string) $config['admin_password']);
$router = new Router();

$router->get('/login', static function () use ($auth, $config): void {
    if ($auth->check()) {
        View::redirect('/');
    }
    View::render('login', ['config' => $config, 'title' => 'Bejelentkezés']);
});

$router->post('/login', static function () use ($auth): void {
    if ($auth->attempt((string) ($_POST['password'] ?? ''))) {
        View::redirect('/');
    }
    View::flash('error', 'Hibás jelszó.');
    View::redirect('/login');
});

$router->get('/logout', static function () use ($auth): void {
    $auth->logout();
    View::redirect('/login');
});

$router->get('/', static function () use ($auth, $switchRepo, $config): void {
    $auth->requireLogin();
    View::render('dashboard', [
        'config' => $config,
        'title' => 'Áttekintés',
        'switches' => $switchRepo->all(),
        'summary' => $switchRepo->dashboardSummary(),
        'flash' => View::consumeFlash(),
    ]);
});

$router->get('/switches', static function () use ($auth, $switchRepo, $config): void {
    $auth->requireLogin();
    View::render('switches/index', [
        'config' => $config,
        'title' => 'Switchek',
        'switches' => $switchRepo->all(),
        'flash' => View::consumeFlash(),
    ]);
});

$router->get('/switches/create', static function () use ($auth, $config): void {
    $auth->requireLogin();
    View::render('switches/form', [
        'config' => $config,
        'title' => 'Új switch',
        'switch' => null,
        'flash' => View::consumeFlash(),
    ]);
});

$router->post('/switches/create', static function () use ($auth, $switchRepo): void {
    $auth->requireLogin();
    try {
        $id = $switchRepo->create($_POST);
        $collector = new CollectorService($switchRepo);
        $collector->collect($id);
        View::flash('success', 'Switch sikeresen hozzáadva és lekérdezve.');
    } catch (Throwable $e) {
        View::flash('error', $e->getMessage());
        View::redirect('/switches/create');
    }
    View::redirect('/switches');
});

$router->get('/switches/edit', static function () use ($auth, $switchRepo, $config): void {
    $auth->requireLogin();
    $switch = $switchRepo->find((int) ($_GET['id'] ?? 0));
    if ($switch === null) {
        View::flash('error', 'Switch nem található.');
        View::redirect('/switches');
    }
    View::render('switches/form', [
        'config' => $config,
        'title' => 'Switch szerkesztése',
        'switch' => $switch,
        'flash' => View::consumeFlash(),
    ]);
});

$router->post('/switches/edit', static function () use ($auth, $switchRepo): void {
    $auth->requireLogin();
    $id = (int) ($_POST['id'] ?? 0);
    try {
        $switchRepo->update($id, $_POST);
        View::flash('success', 'Switch adatai mentve.');
    } catch (Throwable $e) {
        View::flash('error', $e->getMessage());
    }
    View::redirect('/switches/edit?id=' . $id);
});

$router->post('/switches/delete', static function () use ($auth, $switchRepo): void {
    $auth->requireLogin();
    $switchRepo->delete((int) ($_POST['id'] ?? 0));
    View::flash('success', 'Switch törölve.');
    View::redirect('/switches');
});

$router->post('/switches/test', static function () use ($auth, $switchRepo): void {
    $auth->requireLogin();
    $id = (int) ($_POST['id'] ?? 0);
    $switch = $switchRepo->find($id);
    if ($switch === null) {
        View::flash('error', 'Switch nem található.');
        View::redirect('/switches');
    }
    try {
        $info = $switchRepo->clientFor($switch)->testConnection();
        $switchRepo->updateStatus($id, 'online');
        View::flash('success', 'Kapcsolat OK: ' . ($info['model'] ?? 'Switch') . ' / ' . ($info['firmware'] ?? ''));
    } catch (Throwable $e) {
        $switchRepo->updateStatus($id, 'offline');
        View::flash('error', $e->getMessage());
    }
    View::redirect('/switches');
});

$router->get('/switches/show', static function () use ($auth, $switchRepo, $collector, $config): void {
    $auth->requireLogin();
    $id = (int) ($_GET['id'] ?? 0);
    $switch = $switchRepo->find($id);
    if ($switch === null) {
        View::flash('error', 'Switch nem található.');
        View::redirect('/switches');
    }

    $snapshot = null;
    $error = null;
    if (!empty($_GET['refresh'])) {
        try {
            $snapshot = $collector->collect($id);
        } catch (Throwable $e) {
            $error = $e->getMessage();
            $switchRepo->updateStatus($id, 'offline');
        }
    }

    View::render('switches/show', [
        'config' => $config,
        'title' => $switch['name'],
        'switch' => $switch,
        'snapshot' => $snapshot,
        'history' => $switchRepo->latestPortStats($id),
        'error' => $error,
        'flash' => View::consumeFlash(),
    ]);
});

$router->get('/vlan', static function () use ($auth, $switchRepo, $collector, $config): void {
    $auth->requireLogin();
    $id = (int) ($_GET['switch_id'] ?? 0);
    $switches = $switchRepo->all();
    if ($id === 0 && $switches !== []) {
        $id = (int) $switches[0]['id'];
    }

    $switch = $switchRepo->find($id);
    $vlanConfig = null;
    $error = null;

    if ($switch !== null) {
        try {
            $snapshot = $collector->collect($id);
            $vlanConfig = $snapshot['vlan'] ?? null;
        } catch (Throwable $e) {
            $error = $e->getMessage();
            $switchRepo->updateStatus($id, 'offline');
        }
    }

    View::render('vlan/index', [
        'config' => $config,
        'title' => 'VLAN kezelés',
        'switches' => $switches,
        'switch' => $switch,
        'vlanConfig' => $vlanConfig,
        'error' => $error,
        'flash' => View::consumeFlash(),
    ]);
});

$router->post('/vlan/save', static function () use ($auth, $switchRepo): void {
    $auth->requireLogin();
    $id = (int) ($_POST['switch_id'] ?? 0);
    $switch = $switchRepo->find($id);
    if ($switch === null) {
        View::flash('error', 'Switch nem található.');
        View::redirect('/vlan');
    }

    try {
        $portCount = (int) ($_POST['port_count'] ?? 8);
        $pvids = [];
        $vlanRows = json_decode((string) ($_POST['vlan_json'] ?? '[]'), true, 512, JSON_THROW_ON_ERROR);

        for ($i = 1; $i <= $portCount; $i++) {
            $pvids[$i] = (int) ($_POST['pvid'][$i] ?? 1);
        }

        $vlanConfig = [
            'port_count' => $portCount,
            'pvids' => $pvids,
            'vlans' => is_array($vlanRows) ? $vlanRows : [],
        ];

        $switchRepo->clientFor($switch)->saveVlanConfig($vlanConfig);
        View::flash('success', 'VLAN konfiguráció elküldve a switchre.');
    } catch (Throwable $e) {
        View::flash('error', $e->getMessage());
    }

    View::redirect('/vlan?switch_id=' . $id);
});

$router->get('/stats', static function () use ($auth, $switchRepo, $config): void {
    $auth->requireLogin();
    $id = (int) ($_GET['switch_id'] ?? 0);
    $port = (int) ($_GET['port'] ?? 1);
    $switches = $switchRepo->all();
    if ($id === 0 && $switches !== []) {
        $id = (int) $switches[0]['id'];
    }

    $switch = $switchRepo->find($id);
    $history = $switch !== null ? $switchRepo->portHistory($id, $port) : [];
    $latest = $switch !== null ? $switchRepo->latestPortStats($id) : [];

    View::render('stats/index', [
        'config' => $config,
        'title' => 'Statisztikák',
        'switches' => $switches,
        'switch' => $switch,
        'port' => $port,
        'history' => $history,
        'latest' => $latest,
        'flash' => View::consumeFlash(),
    ]);
});

$router->post('/collect/all', static function () use ($auth, $collector): void {
    $auth->requireLogin();
    $results = $collector->collectAll();
    $ok = count(array_filter($results, static fn ($r) => $r['ok']));
    View::flash('success', $ok . ' / ' . count($results) . ' switch frissítve.');
    View::redirect('/');
});

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
