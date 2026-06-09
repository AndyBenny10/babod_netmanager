<section class="page-header">
    <div>
        <h1>VLAN kezelés</h1>
        <p class="muted">802.1Q VLAN és PVID szerkesztése</p>
    </div>
</section>

<section class="card">
    <form method="get" action="/vlan" class="inline-filter">
        <label>
            Switch
            <select name="switch_id" onchange="this.form.submit()">
                <?php foreach ($switches as $item): ?>
                    <option value="<?= (int) $item['id'] ?>" <?= ($switch && (int) $switch['id'] === (int) $item['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($item['name']) ?> (<?= htmlspecialchars($item['host']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
    </form>
</section>

<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($vlanConfig): ?>
<section class="card">
    <h2>PVID (Port VLAN ID)</h2>
    <form method="post" action="/vlan/save" id="vlan-form">
        <input type="hidden" name="switch_id" value="<?= (int) $switch['id'] ?>">
        <input type="hidden" name="port_count" value="<?= (int) $vlanConfig['port_count'] ?>">
        <input type="hidden" name="vlan_json" id="vlan_json" value="">

        <div class="pvid-grid">
            <?php for ($i = 1; $i <= (int) $vlanConfig['port_count']; $i++): ?>
                <label>
                    Port <?= $i ?> PVID
                    <input type="number" min="1" max="4094" name="pvid[<?= $i ?>]"
                           value="<?= (int) ($vlanConfig['pvids'][$i] ?? 1) ?>">
                </label>
            <?php endfor; ?>
        </div>

        <h2>VLAN lista</h2>
        <p class="muted">
            Szürke = nincs tag, zöld = untagged, narancs = tagged.
            VLAN 1-et általában ne töröld teljesen.
        </p>

        <div class="vlan-editor" data-port-count="<?= (int) $vlanConfig['port_count'] ?>">
            <?php foreach ($vlanConfig['vlans'] as $vlanIndex => $vlan): ?>
                <div class="vlan-row" data-vlan-id="<?= (int) $vlan['id'] ?>">
                    <div class="vlan-row-header">
                        <strong>VLAN <?= (int) $vlan['id'] ?></strong>
                        <?php if ((int) $vlan['id'] !== 1): ?>
                            <button type="button" class="link-btn danger remove-vlan">Törlés</button>
                        <?php endif; ?>
                    </div>
                    <div class="port-modes">
                        <?php for ($p = 1; $p <= (int) $vlanConfig['port_count']; $p++):
                            $mode = $vlan['ports'][$p] ?? 'none';
                            ?>
                            <label class="port-mode port-mode-<?= htmlspecialchars($mode) ?>">
                                P<?= $p ?>
                                <select class="vlan-port-mode" data-port="<?= $p ?>">
                                    <option value="none" <?= $mode === 'none' ? 'selected' : '' ?>>-</option>
                                    <option value="untagged" <?= $mode === 'untagged' ? 'selected' : '' ?>>Untagged</option>
                                    <option value="tagged" <?= $mode === 'tagged' ? 'selected' : '' ?>>Tagged</option>
                                </select>
                            </label>
                        <?php endfor; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="form-actions">
            <button type="button" class="btn-secondary" id="add-vlan">+ Új VLAN</button>
            <button type="submit">Alkalmazás a switchre</button>
        </div>
    </form>
</section>
<?php elseif ($switch): ?>
    <section class="card"><p class="muted">Nem sikerült VLAN adatot betölteni.</p></section>
<?php else: ?>
    <section class="card"><p class="muted">Először adj hozzá switchet.</p></section>
<?php endif; ?>
