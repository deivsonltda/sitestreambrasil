<?php
require __DIR__ . '/_guard.php';
require __DIR__ . '/../../src/supabase.php';
require __DIR__ . '/../../src/utils.php';

$cfg = require __DIR__ . '/../../config.php';

/**
 * Monta URL do WhatsApp usando nome público ou @instagram (já pronto)
 * Ex: "Olá, vim por indicação de @lais.ribeiro2 e quero fazer um teste grátis."
 */
function build_whatsapp_url(string $publicRef): string
{
  $cfg = require __DIR__ . '/../../config.php';
  $publicRef = trim($publicRef) ?: 'um afiliado';

  $msg = "Olá, vim por indicação de {$publicRef} e quero fazer um teste grátis.";
  return "https://wa.me/{$cfg['WHATSAPP_NUMBER']}?text=" . urlencode($msg);
}

/**
 * Gera um code bonito e único: base, base2, base3...
 */
function generate_unique_code(string $base): string
{
  $base = slugify($base);
  $code = $base;

  for ($i = 1; $i <= 100; $i++) {
    $exists = sb_request(
      'GET',
      "affiliates?select=id&code=eq." . urlencode($code) . "&limit=1",
      null,
      true
    );
    if (!$exists) return $code;
    $code = $base . $i;
  }

  // fallback extremamente raro
  return $base . '-' . random_code(4);
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    $name  = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $city  = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');

    // Instagram (opcional): salva SEM @, em minúsculo, sem espaços
    $ig = trim($_POST['instagram_username'] ?? '');
    $ig = ltrim($ig, '@');
    $ig = strtolower($ig);
    $ig = preg_replace('/\s+/', '', $ig);

    if ($ig !== '' && !preg_match('/^[a-z0-9._]{2,30}$/', $ig)) {
      throw new Exception("Instagram inválido. Use apenas letras, números, ponto e underline.");
    }

    $pin = trim($_POST['pin'] ?? '');
    if (!preg_match('/^\d{4,8}$/', $pin)) {
      throw new Exception("PIN inválido. Use 4 a 8 dígitos.");
    }

    $type    = $_POST['rule_type'] ?? 'adhesion_fixed';
    $percent = (float)($_POST['percent'] ?? 0);

    if (!$name || !$phone || !$city || !$state) {
      throw new Exception("Preencha todos os campos obrigatórios.");
    }

    // -------- Nome público (o que aparece pro cliente e no zap)
    // prioridade: @instagram -> primeiro nome
    if ($ig) {
      $publicRef = '@' . $ig;
      $displayName = $ig; // base para code
    } else {
      $parts = preg_split('/\s+/', trim($name));
      $displayName = $parts[0] ?? $name;
      $publicRef = $displayName;
    }

    // -------- Code do link (interno, curto e bonito)
    $code = generate_unique_code($displayName);

    // -------- Cria afiliado
    $created = sb_request('POST', 'affiliates', [
      'name' => $name,
      'instagram_username' => ($ig ?: null),
      'phone' => $phone,
      'city' => $city,
      'state' => $state,
      'code' => $code,
      'pin_hash' => password_hash($pin, PASSWORD_BCRYPT),
      'display_name' => $displayName,
    ], true);

    if (!$created || empty($created[0]['id'])) {
      throw new Exception("Não foi possível criar o afiliado (resposta vazia). Verifique o supabase.php (Prefer: return=representation).");
    }

    $affiliateId = $created[0]['id'];

    // -------- Regra de comissão
    $rule = [
      'affiliate_id' => $affiliateId,
      'type' => $type,
      'adhesion_amount' => 10.00,
      'percent' => null
    ];

    if ($type === 'recurring_percent') {
      if ($percent <= 0 || $percent > 1) {
        throw new Exception("Percentual inválido. Use 0.10 para 10%, 0.30 para 30%.");
      }
      $rule['percent'] = $percent;
    }

    sb_request('POST', 'affiliate_commission_rules', $rule, true);

    // -------- Short link (destino WhatsApp)
    $dest = build_whatsapp_url($publicRef);

    sb_request('POST', 'short_links', [
      'affiliate_id' => $affiliateId,
      'code' => $code,
      'destination_url' => $dest,
      'is_active' => true
    ], true);

    // -------- Tela de confirmação (pra você copiar o PIN)
    echo "
<!doctype html>
<html lang='pt-br'>
<head>
  <meta charset='utf-8'>
  <title>Afiliado criado</title>
  <meta name='viewport' content='width=device-width,initial-scale=1'>
  <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css' rel='stylesheet'>
  
</head>
<body class='bg-light'>
  <div class='container py-5' style='max-width:760px;'>
    <div class='bg-white rounded-4 shadow-sm p-4'>
      <h3 class='mb-3'>Afiliado criado com sucesso ✅</h3>

      <p class='mb-1'><strong>Nome público:</strong> " . htmlspecialchars($publicRef) . "</p>
      <p class='mb-1'><strong>Link de indicação:</strong> <code>/a/" . htmlspecialchars($code) . "</code></p>
      <p class='mb-3'><strong>PIN:</strong> <span class='fs-4'>" . htmlspecialchars($pin) . "</span></p>

      <div class='alert alert-warning'>
        ⚠️ Anote esse PIN agora. Ele não pode ser recuperado depois.
      </div>

      <hr>

      <p class='mb-2'><strong>Login do afiliado:</strong> <code>/affiliate/login.php</code></p>

      <div class='mt-3 d-flex gap-2'>
        <a href='/admin/affiliates.php' class='btn btn-primary'>Voltar para afiliados</a>
        <a href='/admin/create_affiliate.php' class='btn btn-outline-secondary'>Criar outro</a>
      </div>
    </div>
  </div>
</body>
</html>
";
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
  <title>Novo afiliado</title>
</head>

<body class="bg-light">
  <div class="container py-4" style="max-width:760px;">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h4 class="mb-0">Novo afiliado</h4>
      <a class="btn btn-outline-secondary" href="/admin/affiliates.php">Voltar</a>
    </div>

    <div class="bg-white rounded-4 shadow-sm p-4">
      <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="post" class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Nome Completo</label>
          <input class="form-control" name="name" required>
        </div>

        <div class="col-md-6">
          <label class="form-label">@ do Instagram (opcional)</label>
          <input class="form-control" name="instagram_username" placeholder="Ex: lais.oficial">
        </div>

        <div class="col-md-6">
          <label class="form-label">Telefone</label>
          <input class="form-control" name="phone" required>
        </div>

        <div class="col-md-4">
          <label class="form-label">Cidade</label>
          <input class="form-control" name="city" required>
        </div>

        <div class="col-md-2">
          <label class="form-label">Estado (UF)</label>
          <input class="form-control" name="state" maxlength="2" required>
        </div>

        <div class="col-md-6">
          <label class="form-label">PIN do afiliado (4 a 8 dígitos)</label>
          <input class="form-control" name="pin" inputmode="numeric" minlength="4" maxlength="8" required>
          <small class="text-muted">Você envia esse PIN pro afiliado fazer login.</small>
        </div>

        <div class="col-md-6">
          <label class="form-label">Tipo de comissão</label>
          <select class="form-select" name="rule_type" id="rule_type">
            <option value="adhesion_fixed">R$ 10 apenas na adesão</option>
            <option value="recurring_percent">Percentual recorrente</option>
          </select>
        </div>

        <div class="col-12" id="percent_wrap" style="display:none;">
          <label class="form-label">Percentual (ex: 0.30 = 30%)</label>
          <input class="form-control" name="percent" placeholder="0.30">
        </div>

        <div class="col-12">
          <button class="btn btn-primary">Criar afiliado</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    const sel = document.getElementById('rule_type');
    const wrap = document.getElementById('percent_wrap');

    function sync() {
      wrap.style.display = (sel.value === 'recurring_percent') ? 'block' : 'none';
    }
    sel.addEventListener('change', sync);
    sync();
  </script>
</body>

</html>