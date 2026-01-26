<?php
session_start();
$cfg = require __DIR__ . '/../../config.php';

if (!empty($_SESSION['admin'])) {
  header("Location: /admin/affiliates.php");
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $pass = $_POST['password'] ?? '';
  if (hash_equals($cfg['ADMIN_PASSWORD'], $pass)) {
    $_SESSION['admin'] = true;
    header("Location: /admin/affiliates.php");
    exit;
  }
  $error = "Senha inválida.";
}
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="/assets/css/global.css" rel="stylesheet">
  <title>Login Admin</title>
</head>
<body class="bg-light">
<div class="container py-5" style="max-width:520px;">
  <div class="bg-white rounded-4 shadow-sm p-4">
    <h4 class="mb-1">Admin</h4>
    <div class="text-muted mb-3">Entre para gerenciar afiliados e pagamentos.</div>

    <?php if (!empty($error)): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post">
      <label class="form-label">Senha</label>
      <input class="form-control mb-3" type="password" name="password" required>
      <button class="btn btn-primary w-100">Entrar</button>
    </form>
  </div>
</div>
</body>
</html>