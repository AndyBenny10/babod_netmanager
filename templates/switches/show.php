<section class="page-header">
    <div>
        <h1><?= htmlspecialchars($switch['name']) ?></h1>
        <p class="muted"><code><?= htmlspecialchars($switch['host']) ?></code> · <?= htmlspecialchars($switch['api_type'] ?? '') ?></p>
    </div>
    <div class="header-actions">
        <a class="btn-secondary" href="/switches/show?id=<?= (int) $switch['id'] ?>&refresh=1">Frissítés</a>
        <a class="btn" href="/vlan?switch_id=<?= (int) $switch['id'] ?>">VLAN</a>
        <a class="btn-secondary" href="/stats?switch_id=<?= (int) $switch['id'] ?>">Statisztika</a>
    </div>
</section>

<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($snapshot): ?>
<section class="card">
    <h2>Rendszer</h2>
    <div class="info-grid">
        <div><span>Modell</span><strong><?= htmlspecialchars($snapshot['system']['model'] ?? '') ?></strong></div>
        <div><span>Firmware</span><strong><?= htmlspecialchars($snapshot['system']['firmware'] ?? '') ?></strong></div>
        <div><span>Hostname</span><strong><?= htmlspecialchars($snapshot['system']['hostname'] ?? '') ?></strong></div>
        <div><span>MAC</span><strong><?= htmlspecialchars($snapshot['system']['mac'] ?? '') ?></strong></div>
        <div><span>IP</span><strong><?= htmlspecialchars($snapshot['system']['ip'] ?? '') ?></strong></div>
        <div><span>Átjáró</span><strong><?= htmlspecialchars($snapshot['system']['gateway'] ?? '') ?></strong></div>
    </div>
</section>

<section class="card">
    <h2>Portok</h2>
    <table>
        <thead>
        <tr>
            <th>Port</th>
            <th>Állapot</th>
            <th>Sebesség</th>
            <th>RX csomag</th>
            <th>TX csomag</th>
            <th>Loop</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($snapshot['ports'] as $port): ?>
            <tr>
                <td>P<?= (int) $port['index'] ?></td>
                <td><span class="badge badge-<?= strtolower($port['status']) === 'up' ? 'ok' : 'bad' ?>"><?= htmlspecialchars($port['status']) ?></span></td>
                <td><?= htmlspecialchars($port['speed']) ?></td>
                <td><?= number_format((int) $port['rx_packets']) ?></td>
                <td><?= number_format((int) $port['tx_packets']) ?></td>
                <td><?= htmlspecialchars($port['loop'] ?? '-') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
<?php elseif ($history !== []): ?>
<section class="card">
    <h2>Utolsó mentett port adatok</h2>
    <table>
        <thead>
        <tr>
            <th>Port</th>
            <th>Állapot</th>
            <th>Sebesség</th>
            <th>RX</th>
            <th>TX</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($history as $port): ?>
            <tr>
                <td>P<?= (int) $port['port_index'] ?></td>
                <td><?= htmlspecialchars($port['status']) ?></td>
                <td><?= htmlspecialchars($port['speed']) ?></td>
                <td><?= number_format((int) $port['rx_packets']) ?></td>
                <td><?= number_format((int) $port['tx_packets']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
<?php else: ?>
    <section class="card">
        <p class="muted">Kattints a Frissítés gombra az élő adatok lekéréséhez.</p>
    </section>
<?php endif; ?>
