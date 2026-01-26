<?php
require __DIR__ . '/_guard.php';
require __DIR__ . '/../../src/supabase.php';
require __DIR__ . '/../../src/utils.php';

$id = $_GET['id'] ?? '';
if (!$id) {
  http_response_code(400);
  echo "missing id";
  exit;
}

$error = null;

$affs = sb_request(
  'GET',
  "affiliates?select=id,name,instagram_username,code&order=created_at.desc",
  null,
  true
);

$c = sb_request(
  'GET',
  "customers?select=id,name,phone,affiliate_id,status,wa_from,wa_chat_id&id=eq." . urlencode($id) . "&limit=1",
  null,
  true
);

if (!$c) {
  http_response_code(404);
  echo "cliente não encontrado";
  exit;
}
$c = $c[0];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $affiliateId = $_POST['affiliate_id'] ?? null;

    if (!$name || !$phone) {
      throw new Exception("Preencha nome e telefone.");
    }

    $digits = normalize_phone_digits($phone);

    // Sem LID: prioridade máxima continua sendo o telefone,
    // então limpamos apenas wa_from (e, se existir no seu schema, wa_chat_id)
    $patch = [
      'name' => $name,
      'phone' => $digits,
      'wa_from' => null,
      'affiliate_id' => ($affiliateId ?: null),
    ];

    // Se sua tabela tiver wa_chat_id, pode zerar também pra evitar conflito de exibição
    // (Se não existir, não coloque no PATCH pra não estourar "column does not exist")
    if (array_key_exists('wa_chat_id', $c)) {
      $patch['wa_chat_id'] = null;
    }

    sb_request('PATCH', "customers?id=eq." . urlencode($id), $patch, true);

    header("Location: /admin/customers.php");
    exit;
  } catch (Exception $e) {
    $error = $e->getMessage();
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
  <title>Editar cliente</title>
</head>

<body class="bg-light">
  <div class="container py-4" style="max-width:760px;">

    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h4 class="mb-0">Editar cliente</h4>
        <div class="text-muted">Atualize os dados do cliente</div>
      </div>

      <a class="btn btn-outline-secondary" href="/admin/customers.php">
        <i class="bi bi-arrow-left me-1"></i>Voltar
      </a>
    </div>

    <div class="bg-white rounded-4 shadow-sm p-4">

      <?php if ($error): ?>
        <div class="alert alert-danger">
          <?= htmlspecialchars($error) ?>
        </div>
      <?php endif; ?>

      <form method="post" class="row g-3">

        <div class="col-md-6">
          <label class="form-label">Nome</label>
          <input
            class="form-control"
            name="name"
            required
            value="<?= htmlspecialchars($c['name'] ?? '') ?>">
        </div>

        <div class="col-md-6">
          <label class="form-label">Telefone</label>
          <input
            class="form-control"
            name="phone"
            required
            value="<?= htmlspecialchars($c['phone'] ?? '') ?>">
        </div>

        <div class="col-12">
          <label class="form-label">Indicador</label>
          <select class="form-select" name="affiliate_id">
            <option value="">— Sem indicador —</option>
            <?php foreach ($affs as $a): ?>
              <?php
              $label = $a['instagram_username']
                ? '@' . $a['instagram_username']
                : ($a['name'] ?? $a['code'] ?? '');
              $sel = (($c['affiliate_id'] ?? '') === $a['id']) ? 'selected' : '';
              ?>
              <option value="<?= htmlspecialchars($a['id']) ?>" <?= $sel ?>>
                <?= htmlspecialchars($label) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12 d-flex gap-2">
          <button class="btn btn-primary">
            <i class="bi bi-check2-circle me-1"></i>Salvar
          </button>

          <a class="btn btn-outline-secondary" href="/admin/customers.php">
            Cancelar
          </a>
        </div>

      </form>
    </div>

  </div>
</body>

</html>