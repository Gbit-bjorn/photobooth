<?php
require __DIR__ . '/../app/bootstrap.php';

$fout = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (auth_login((string)($_POST['wachtwoord'] ?? ''))) {
        header('Location: /admin/');
        exit;
    }
    $fout = 'Onjuist wachtwoord.';
}
if (auth_check()) {
    header('Location: /admin/');
    exit;
}

$ev = pb_event();
page_header('Login');
?>
<main class="wrap">
  <h1 class="display"><?= htmlspecialchars($ev['couple']) ?></h1>
  <?php leaf_divider(); ?>
  <p class="subtitle">Beheer</p>
  <form class="card" method="post" action="/admin/login.php" style="margin-top: var(--space-3)">
    <label for="wachtwoord">Wachtwoord</label>
    <input type="password" id="wachtwoord" name="wachtwoord" autocomplete="current-password" autofocus>
    <?php if ($fout !== ''): ?><p class="field-hint" style="color: var(--c-terracotta)"><?= htmlspecialchars($fout) ?></p><?php endif; ?>
    <button class="btn" type="submit" style="margin-top: var(--space-2)">Inloggen</button>
  </form>
</main>
<?php page_footer(); ?>
