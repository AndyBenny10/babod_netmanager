<section class="page-header">
    <div>
        <h1>Switchek</h1>
        <p class="muted">Hozzáadott Zyxel eszközök kezelése</p>
    </div>
    <a class="btn" href="/switches/create">+ Új switch</a>
</section>

<section class="card">
    <?php if ($switches === []): ?>
        <p class="muted">Nincs switch.</p>
    <?php else: ?>
        <table>
            <thead>
            <tr>
                <th>Név</th>
                <th>Host</th>
                <th>Hely</th>
                <th>API</th>
                <th>HTTPS</th>
                <th>Állapot</th>
                <th>Műveletek</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($switches as $switch): ?>
                <tr>
                    <td><?= htmlspecialchars($switch['name']) ?></td>
                    <td><code><?= htmlspecialchars($switch['host']) ?></code></td>
                    <td><?= htmlspecialchars($switch['location'] ?? '') ?></td>
                    <td><?= htmlspecialchars($switch['api_type'] ?? '') ?></td>
                    <td><?= !empty($switch['use_https']) ? 'igen' : 'nem' ?></td>
                    <td><span class="badge badge-<?= ($switch['last_status'] ?? '') === 'online' ? 'ok' : 'bad' ?>">
                        <?= htmlspecialchars($switch['last_status'] ?? '-') ?>
                    </span></td>
                    <td class="actions">
                        <a href="/switches/show?id=<?= (int) $switch['id'] ?>">Megnyitás</a>
                        <a href="/switches/edit?id=<?= (int) $switch['id'] ?>">Szerkesztés</a>
                        <form method="post" action="/switches/test" class="inline">
                            <input type="hidden" name="id" value="<?= (int) $switch['id'] ?>">
                            <button type="submit" class="link-btn">Teszt</button>
                        </form>
                        <form method="post" action="/switches/delete" class="inline" onsubmit="return confirm('Biztosan törlöd?')">
                            <input type="hidden" name="id" value="<?= (int) $switch['id'] ?>">
                            <button type="submit" class="link-btn danger">Törlés</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
