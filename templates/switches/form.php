<?php $isEdit = $switch !== null; ?>
<section class="page-header">
    <h1><?= $isEdit ? 'Switch szerkesztése' : 'Új switch' ?></h1>
</section>

<section class="card">
    <form method="post" action="<?= $isEdit ? '/switches/edit' : '/switches/create' ?>" class="form-grid">
        <?php if ($isEdit): ?>
            <input type="hidden" name="id" value="<?= (int) $switch['id'] ?>">
        <?php endif; ?>

        <label>
            Megjelenő név
            <input type="text" name="name" required value="<?= htmlspecialchars($switch['name'] ?? '') ?>">
        </label>

        <label>
            IP cím / hostname
            <input type="text" name="host" required placeholder="192.168.1.3"
                   value="<?= htmlspecialchars($switch['host'] ?? '') ?>">
        </label>

        <label>
            Switch admin jelszó
            <input type="password" name="password" <?= $isEdit ? '' : 'required' ?>
                   placeholder="<?= $isEdit ? 'Hagyd üresen, ha nem változik' : '' ?>">
        </label>

        <label>
            API típus
            <select name="api_type">
                <option value="">Automatikus felismerés</option>
                <option value="modern" <?= ($switch['api_type'] ?? '') === 'modern' ? 'selected' : '' ?>>Modern (GS1200-8V3 / XGS)</option>
                <option value="legacy" <?= ($switch['api_type'] ?? '') === 'legacy' ? 'selected' : '' ?>>Legacy (régebbi GS1200)</option>
            </select>
        </label>

        <label class="checkbox">
            <input type="checkbox" name="use_https" value="1"
                <?= ($isEdit ? !empty($switch['use_https']) : true) ? 'checked' : '' ?>>
            HTTPS használata (GS1200-8V3 esetén ajánlott)
        </label>

        <label>
            Hely / megjegyzés helye
            <input type="text" name="location" value="<?= htmlspecialchars($switch['location'] ?? '') ?>">
        </label>

        <label class="full">
            Megjegyzés
            <textarea name="notes" rows="3"><?= htmlspecialchars($switch['notes'] ?? '') ?></textarea>
        </label>

        <div class="form-actions full">
            <button type="submit">Mentés</button>
            <a class="btn-secondary" href="/switches">Mégse</a>
        </div>
    </form>
</section>
