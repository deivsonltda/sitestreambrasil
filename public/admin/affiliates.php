<?php
require __DIR__ . '/_guard.php';
require __DIR__ . '/../../src/supabase.php';
$cfg = require __DIR__ . '/../../config.php';

$affs = sb_request('GET', "affiliates?select=*&order=created_at.desc", null, true);

/**
 * Normaliza telefone BR para:
 * - display: (DD) 9XXXX-XXXX
 * - e164: 55 + DDD + 9XXXXXXXX   (pra wa.me)
 *
 * Aceita:
 * - 5581998517063 (13) -> ok
 * - 558198517063  (12) -> insere 9 -> ok
 * - 81998517063   (11) -> ok
 * - 818517063     (10) -> insere 9 -> ok
 * - 5555984561245 (13) -> DDI 55 + DDD 55 -> ok
 */
function br_phone_normalize($raw)
{
  $raw = (string)$raw;
  $digits = preg_replace('/\D+/', '', $raw);

  if ($digits === '') {
    return ['ok' => false, 'digits' => '', 'national' => '', 'e164' => '', 'display' => ''];
  }

  // Se começa com 55 e tem 12 ou 13 dígitos -> remove DDI
  if (str_starts_with($digits, '55') && (strlen($digits) === 12 || strlen($digits) === 13)) {
    $national = substr($digits, 2); // DDD + número
  } else {
    $national = $digits; // veio sem DDI
  }

  // Se ficou com 10 dígitos (DDD + 8), insere 9 após DDD
  if (strlen($national) === 10) {
    $national = substr($national, 0, 2) . '9' . substr($national, 2);
  }

  // Se não virou 11, não dá pra garantir formatação
  if (strlen($national) !== 11) {
    return [
      'ok' => false,
      'digits' => $digits,
      'national' => $national,
      'e164' => '',
      'display' => $digits,
    ];
  }

  $ddd = substr($national, 0, 2);
  $num = substr($national, 2); // 9XXXXXXXX

  $display = "($ddd) " . substr($num, 0, 5) . "-" . substr($num, 5, 4);
  $e164 = '55' . $national;

  return [
    'ok' => true,
    'digits' => $digits,
    'national' => $national,
    'e164' => $e164,
    'display' => $display,
  ];
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

  <title>Afiliados</title>
</head>

<body class="bg-light">
  <div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h4 class="mb-0">Afiliados</h4>

      <a class="btn btn-outline-danger" href="/admin/logout.php">
        Sair
      </a>

      <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary" href="/admin/customers.php">Clientes</a>
        <a class="btn btn-primary" href="/admin/create_affiliate.php">+ Novo afiliado</a>
      </div>
    </div>

    <div class="bg-white rounded-4 shadow-sm p-3">
      <div class="table-responsive">
        <table class="table align-middle">
          <thead>
            <tr>
              <th>Nome</th>
              <th>Cidade/UF</th>
              <th>Telefone</th>
              <th>Link</th>
              <th>Painel</th>
              <th class="text-end">Ações</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($affs as $a): ?>
              <?php
              $norm = br_phone_normalize($a['phone'] ?? '');
              ?>
              <tr>
                <td><?= htmlspecialchars($a['name']) ?></td>
                <td><?= htmlspecialchars($a['city']) ?>/<?= htmlspecialchars($a['state']) ?></td>

                <td>
                  <?php if ($norm['ok']): ?>
                    <a class="text-decoration-none" target="_blank"
                      href="https://wa.me/<?= htmlspecialchars($norm['e164']) ?>">
                      <i class="bi bi-whatsapp text-success me-1"></i><?= htmlspecialchars($norm['display']) ?>
                    </a>
                  <?php else: ?>
                    <span class="text-muted"><?= htmlspecialchars($a['phone'] ?? '—') ?></span>
                  <?php endif; ?>
                </td>

                <td>
                  <a target="_blank" href="<?= htmlspecialchars($cfg['BASE_URL'] . '/a/' . $a['code']) ?>">
                    <?= htmlspecialchars('/a/' . $a['code']) ?>
                  </a>
                </td>

                <td>
                  <a class="btn btn-sm btn-outline-primary" target="_blank"
                    href="/affiliate/?code=<?= urlencode($a['code']) ?>">
                    Abrir painel
                  </a>
                </td>

                <td class="text-end">
                  <div class="d-flex justify-content-end gap-2 flex-wrap">
                    <a class="btn btn-sm btn-outline-primary"
                      href="/admin/edit_affiliate.php?id=<?= urlencode($a['id']) ?>">
                      <i class="bi bi-pencil-square me-1"></i>Editar
                    </a>

                    <button class="btn btn-sm btn-outline-danger"
                      data-bs-toggle="modal"
                      data-bs-target="#deleteModal"
                      data-affiliate-id="<?= htmlspecialchars($a['id']) ?>"
                      data-affiliate-name="<?= htmlspecialchars($a['name']) ?>">
                      Excluir
                    </button>
                  </div>
                </td>

              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Modal Excluir -->
  <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <form class="modal-content" method="post" action="/admin/delete_affiliate.php" id="deleteForm">
        <div class="modal-header">
          <h5 class="modal-title">Excluir afiliado</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>

        <div class="modal-body">
          <p class="mb-2">
            Você está prestes a excluir: <b id="delName">—</b>
          </p>

          <div class="alert alert-warning">
            Essa ação remove também links, regras e vínculos (cascade).
          </div>

          <input type="hidden" name="affiliate_id" id="delId">

          <label class="form-label">Digite <b>excluir</b> para confirmar</label>
          <input class="form-control" name="confirm" id="confirmInput" autocomplete="off" required>

          <small class="text-muted">Só será permitido se você digitar exatamente “excluir”.</small>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-danger" id="confirmBtn" disabled>Excluir</button>
        </div>
      </form>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    const deleteModal = document.getElementById('deleteModal');
    const delName = document.getElementById('delName');
    const delId = document.getElementById('delId');
    const confirmInput = document.getElementById('confirmInput');
    const confirmBtn = document.getElementById('confirmBtn');

    deleteModal.addEventListener('show.bs.modal', (event) => {
      const btn = event.relatedTarget;
      const id = btn.getAttribute('data-affiliate-id');
      const name = btn.getAttribute('data-affiliate-name');

      delName.textContent = name;
      delId.value = id;

      confirmInput.value = '';
      confirmBtn.disabled = true;

      setTimeout(() => confirmInput.focus(), 150);
    });

    confirmInput.addEventListener('input', () => {
      confirmBtn.disabled = (confirmInput.value.trim().toLowerCase() !== 'excluir');
    });
  </script>

</body>

</html>