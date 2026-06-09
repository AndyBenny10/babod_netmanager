<section class="login-card">
    <h1><?= htmlspecialchars($config['app_name']) ?></h1>
    <p class="muted">Zyxel GS1200 központi kezelő</p>
    <form method="post" action="/login">
        <label for="password">Admin jelszó</label>
        <input type="password" id="password" name="password" required autofocus>
        <button type="submit">Bejelentkezés</button>
    </form>
</section>
