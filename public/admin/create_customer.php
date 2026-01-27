<?php
require __DIR__ . '/_guard.php';
require __DIR__ . '/../../src/supabase.php';

$cfg = require __DIR__ . '/../../config.php';
$error = null;

// Carrega afiliados para o select
$affs = sb_request(
  'GET',
  "affiliates?select=id,name,display_name,instagram_username,code&order=created_at.desc",
  null,
  true
);

function affiliate_label($a)
{
  $ig = $a['instagram_username'] ?? null;
  if ($ig) return '@' . $ig;
  $dn = $a['display_name'] ?? null;
  if ($dn) return $dn;
  return $a['name'] ?? 'Afiliado';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    $name  = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $affiliateId = trim($_POST['affiliate_id'] ?? '');

    if (!$name || !$phone || !$affiliateId) {
      throw new Exception("Preencha Nome, Telefone e Indicador.");
    }

    // 1) cria customer (sem endereço/cidade/estado)
    $customerCreated = sb_request('POST', 'customers', [
      'name' => $name,
      'phone' => $phone,
      'affiliate_id' => $affiliateId
    ], true);

    if (!$customerCreated || empty($customerCreated[0]['id'])) {
      throw new Exception("Não foi possível criar o cliente (resposta vazia).");
    }

    $customerId = $customerCreated[0]['id'];

    // 2) cria subscription (mensalidade padrão)
    sb_request('POST', 'subscriptions', [
      'customer_id' => $customerId,
      'status' => 'active',
      'monthly_price' => 19.90,
      'started_at' => gmdate('c')
    ], true);

    header("Location: /customers.php?created=1");
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
  <link href="/assets/css/global.css" rel="stylesheet">
  <title>Novo cliente</title>
</head>

<body class="bg-light">
  <div class="container py-4" style="max-width:760px;">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h4 class="mb-0">Novo cliente</h4>
      <a class="btn btn-outline-secondary" href="/customers.php">Voltar</a>
    </div>

    <div class="bg-white rounded-4 shadow-sm p-4">
      <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="post" class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Nome</label>
          <input class="form-control" name="name" required>
        </div>

        <div class="col-md-6">
          <label class="form-label">Telefone</label>
          <input class="form-control" name="phone" required>
        </div>

        <div class="col-12">
          <label class="form-label">Indicador (Afiliado)</label>
          <select class="form-select" name="affiliate_id" required>
            <option value="">Selecione...</option>
            <?php foreach ($affs as $a): ?>
              <option value="<?= htmlspecialchars($a['id']) ?>">
                <?= htmlspecialchars(affiliate_label($a)) ?> (<?= htmlspecialchars($a['code']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
          <small class="text-muted">Vamos usar @ do Instagram se existir, senão o primeiro nome.</small>
        </div>

        <div class="col-12">
          <button class="btn btn-primary">Criar cliente</button>
        </div>
      </form>
    </div>
  </div>
</body>

</html>