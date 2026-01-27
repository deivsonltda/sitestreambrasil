<?php
require __DIR__ . '/_guard.php';
require __DIR__ . '/../../src/supabase.php';
require __DIR__ . '/../../src/utils.php';

function clean_code($s)
{
  $s = (string)$s;
  $s = trim($s);
  $s = preg_replace('/\s+/', '', $s);
  $s = strtolower($s);
  $s = preg_replace('/[^a-z0-9\_\-]/', '', $s);
  return $s;
}

$id = trim($_GET['id'] ?? '');
$err = '';
$okMsg = '';
$affiliate = null;

if (!$id) {
  $err = 'ID do afiliado não informado.';
} else {
  $rows = sb_request(
    'GET',
    "affiliates?select=*&id=eq." . urlencode($id) . "&limit=1",
    null,
    true
  );

  if (!$rows) {
    $err = 'Afiliado não encontrado.';
  } else {
    $affiliate = $rows[0];
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $affiliate) {
  $name = trim($_POST['name'] ?? '');
  $phone = trim($_POST['phone'] ?? '');
  $city = trim($_POST['city'] ?? '');
  $state = strtoupper(trim($_POST['state'] ?? ''));
  $code = clean_code($_POST['code'] ?? '');
  $instagram = trim($_POST['instagram_username'] ?? '');

  if ($instagram && str_starts_with($instagram, '@')) {
    $instagram = substr($instagram, 1);
  }

  if ($name === '') {
    $err = 'Informe o nome.';
  } elseif ($state && strlen($state) !== 2) {
    $err = 'UF inválido.';
  } elseif ($code === '') {
    $err = 'Informe o código.';
  } else {
    try {
      sb_request(
        'PATCH',
        "affiliates?id=eq." . urlencode($id),
        [
          'name' => $name,
          'phone' => $phone,
          'city' => $city,
          'state' => $state,
          'code' => $code,
          'instagram_username' => $instagram
        ],
        true
      );

      $rows = sb_request(
        'GET',
        "affiliates?select=*&id=eq." . urlencode($id) . "&limit=1",
        null,
        true
      );

      $affiliate = $rows[0];
      $okMsg = 'Afiliado atualizado com sucesso.';
    } catch (Throwable $e) {
      $err = 'Erro ao salvar alterações.';
    }
  }
}
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="/assets/css/global.css" rel="stylesheet">

  <title>Editar afiliado</title>
</head>

<body class="bg-light">
  <div class="container py-4" style="max-width:900px;">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h4 class="mb-0">Editar afiliado</h4>

      <a class="btn btn-outline-secondary" href="/affiliates.php">
        Voltar
      </a>
    </div>

    <?php if ($err): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($err) ?></div>
    <?php endif; ?>

    <?php if ($okMsg): ?>
      <div class="alert alert-success"><?= htmlspecialchars($okMsg) ?></div>
    <?php endif; ?>

    <?php if ($affiliate): ?>
      <div class="bg-white rounded-4 shadow-sm p-4">
        <form method="post" autocomplete="off">
          <div class="row g-3">

            <div class="col-md-6">
              <label class="form-label">Nome</label>
              <input class="form-control" name="name" required
                     value="<?= htmlspecialchars($affiliate['name']) ?>">
            </div>

            <div class="col-md-6">
              <label class="form-label">Telefone</label>
              <input class="form-control" name="phone"
                     value="<?= htmlspecialchars($affiliate['phone'] ?? '') ?>">
            </div>

            <div class="col-md-6">
              <label class="form-label">Cidade</label>
              <input class="form-control" name="city"
                     value="<?= htmlspecialchars($affiliate['city'] ?? '') ?>">
            </div>

            <div class="col-md-2">
              <label class="form-label">UF</label>
              <input class="form-control text-uppercase" maxlength="2" name="state"
                     value="<?= htmlspecialchars($affiliate['state'] ?? '') ?>">
            </div>

            <div class="col-md-4">
              <label class="form-label">Código do link</label>
              <input class="form-control" name="code" required
                     value="<?= htmlspecialchars($affiliate['code']) ?>">
            </div>

            <div class="col-md-6">
              <label class="form-label">Instagram</label>
              <input class="form-control" name="instagram_username"
                     value="<?= htmlspecialchars($affiliate['instagram_username'] ?? '') ?>">
            </div>

            <div class="col-12 text-end mt-3">
              <a href="/affiliates.php" class="btn btn-outline-secondary me-2">
                Cancelar
              </a>
              <button class="btn btn-primary">
                <i class="bi bi-check2-circle me-1"></i>
                Salvar alterações
              </button>
            </div>

          </div>
        </form>
      </div>
    <?php endif; ?>

  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>