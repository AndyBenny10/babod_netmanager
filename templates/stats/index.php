<section class="page-header">
    <div>
        <h1>Statisztikák</h1>
        <p class="muted">Port forgalom és állapot történet</p>
    </div>
</section>

<section class="card">
    <form method="get" action="/stats" class="inline-filter">
        <label>
            Switch
            <select name="switch_id" onchange="this.form.submit()">
                <?php foreach ($switches as $item): ?>
                    <option value="<?= (int) $item['id'] ?>" <?= ($switch && (int) $switch['id'] === (int) $item['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($item['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Port
            <select name="port" onchange="this.form.submit()">
                <?php for ($i = 1; $i <= 10; $i++): ?>
                    <option value="<?= $i ?>" <?= $port === $i ? 'selected' : '' ?>>P<?= $i ?></option>
                <?php endfor; ?>
            </select>
        </label>
    </form>
</section>

<?php if ($switch && $latest !== []): ?>
<section class="card">
    <h2>Aktuális port állapotok</h2>
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
        <?php foreach ($latest as $row): ?>
            <tr>
                <td>P<?= (int) $row['port_index'] ?></td>
                <td><?= htmlspecialchars($row['status']) ?></td>
                <td><?= htmlspecialchars($row['speed']) ?></td>
                <td><?= number_format((int) $row['rx_packets']) ?></td>
                <td><?= number_format((int) $row['tx_packets']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
<?php endif; ?>

<section class="card">
    <h2>Port <?= (int) $port ?> történet</h2>
    <?php if ($history === []): ?>
        <p class="muted">Még nincs mentett mérési adat. Frissíts egy switchet az áttekintésből vagy a részletek oldalról.</p>
    <?php else: ?>
        <canvas id="traffic-chart" height="120"
                data-labels='<?= json_encode(array_column($history, 'collected_at'), JSON_THROW_ON_ERROR) ?>'
                data-rx='<?= json_encode(array_map('intval', array_column($history, 'rx_packets')), JSON_THROW_ON_ERROR) ?>'
                data-tx='<?= json_encode(array_map('intval', array_column($history, 'tx_packets')), JSON_THROW_ON_ERROR) ?>'>
        </canvas>
        <table class="compact-top">
            <thead>
            <tr>
                <th>Időpont</th>
                <th>Állapot</th>
                <th>RX</th>
                <th>TX</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach (array_reverse($history) as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['collected_at']) ?></td>
                    <td><?= htmlspecialchars($row['status']) ?></td>
                    <td><?= number_format((int) $row['rx_packets']) ?></td>
                    <td><?= number_format((int) $row['tx_packets']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
