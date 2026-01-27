<?php
$cfg = require __DIR__ . '/../config.php';

// Detecta o host atual
$host = $_SERVER['HTTP_HOST'] ?? '';

// Define URLs base conforme subdomínio
$baseAdmin = $cfg['BASE_ADMIN'] ?? 'https://admin.streambrasil.online';
$baseAff   = $cfg['BASE_AFF']   ?? 'https://afiliado.streambrasil.online';

// Se estiver no admin, já manda pro login admin
if (str_starts_with($host, 'admin.')) {
  header("Location: {$baseAdmin}/login.php");
  exit;
}

// Se estiver no afiliado, já manda pro login afiliado
if (str_starts_with($host, 'afiliado.')) {
  header("Location: {$baseAff}/login.php");
  exit;
}
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="/assets/css/global.css" rel="stylesheet">
  <title>Sistema de Afiliados</title>
</head>
<body class="bg-light">
<div class="container py-5">
  <div class="row g-4">
    <div class="col-md-7">
      <div class="p-4 bg-white rounded-4 shadow-sm">
        <h3 class="mb-2">Sistema de Afiliados</h3>
        <p class="text-muted mb-3">Painel administrativo e área do afiliado separados por domínio.</p>

        <div class="d-flex gap-2">
          <a class="btn btn-primary" href="<?= htmlspecialchars($baseAdmin) ?>/login.php">
            Entrar como Admin
          </a>

          <a class="btn btn-outline-secondary" href="<?= htmlspecialchars($baseAff) ?>/login.php">
            Painel do Afiliado
          </a>
        </div>

        <hr>

        <small class="text-muted">
          WhatsApp destino: <?= htmlspecialchars($cfg['WHATSAPP_NUMBER'] ?? '+55 81 98452-1498') ?>
        </small>
      </div>
    </div>
  </div>
</div>
</body>
</html>