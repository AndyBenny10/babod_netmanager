<section class="page-header">
    <div>
        <h1>Áttekintés</h1>
        <p class="muted">Központi nézet a Zyxel GS1200 switchekről</p>
    </div>
    <form method="post" action="/collect/all">
        <button type="submit" class="btn-secondary">Összes frissítése</button>
    </form>
</section>

<div class="stats-grid">
    <div class="stat-card">
        <span class="stat-label">Összes switch</span>
        <strong><?= (int) $summary['total'] ?></strong>
    </div>
    <div class="stat-card online">
        <span class="stat-label">Elérhető</span>
        <strong><?= (int) $summary['online'] ?></strong>
    </div>
    <div class="stat-card offline">
        <span class="stat-label">Nem elérhető</span>
        <strong><?= (int) $summary['offline'] ?></strong>
    </div>
</div>

<section class="card">
    <div class="card-header">
        <h2>Switchek</h2>
        <a class="btn" href="/switches/create">+ Új switch</a>
    </div>
    <?php if ($switches === []): ?>
        <p class="muted">Még nincs switch hozzáadva. Kezdd az <a href="/switches/create">első GS1200-8V3</a> felvételével.</p>
    <?php else: ?>
        <table>
            <thead>
            <tr>
                <th>Név</th>
                <th>IP / Host</th>
                <th>API</th>
                <th>Állapot</th>
                <th>Utolsó látás</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($switches as $switch): ?>
                <tr>
                    <td><?= htmlspecialchars($switch['name']) ?></td>
                    <td><code><?= htmlspecialchars($switch['host']) ?></code></td>
                    <td><?= htmlspecialchars($switch['api_type'] ?? 'auto') ?></td>
                    <td><span class="badge badge-<?= ($switch['last_status'] ?? '') === 'online' ? 'ok' : 'bad' ?>">
                        <?= htmlspecialchars($switch['last_status'] ?? 'ismeretlen') ?>
                    </span></td>
                    <td><?= htmlspecialchars($switch['last_seen_at'] ?? '-') ?></td>
                    <td><a href="/switches/show?id=<?= (int) $switch['id'] ?>">Részletek</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
