<?php
require __DIR__ . '/_guard.php';
require __DIR__ . '/../../src/supabase.php';
require __DIR__ . '/../../src/utils.php';

/**
 * Normaliza telefone BR para:
 * - display: (DD) 9XXXX-XXXX
 * - e164: 55 + DDD + 9XXXXXXXX   (pra wa.me)
 */
function br_phone_normalize($raw)
{
  $raw = (string)$raw;
  $raw = str_replace(['@c.us'], '', $raw);
  $digits = preg_replace('/\D+/', '', $raw);

  if ($digits === '') {
    return ['ok' => false, 'digits' => '', 'national' => '', 'e164' => '', 'display' => ''];
  }

  if (str_starts_with($digits, '55') && (strlen($digits) === 12 || strlen($digits) === 13)) {
    $national = substr($digits, 2);
  } else {
    $national = $digits;
  }

  // BR: se vier 10 dígitos (DDD + 8), força 9 na frente
  if (strlen($national) === 10) {
    $national = substr($national, 0, 2) . '9' . substr($national, 2);
  }

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
  $num = substr($national, 2);

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

// REMOVIDO wa_lid do select (não existe mais no projeto)
$customers = sb_request(
  'GET',
  "customers?select=id,name,phone,wa_from,wa_chat_id,status,affiliate_id,indicator_slug,created_at,last_message_at&order=last_message_at.desc.nullslast,created_at.desc",
  null,
  true
);

function customer_status_badge($status)
{
  $status = $status ?: 'trial';
  if ($status === 'active') return '<span class="badge text-bg-success">PAGO</span>';
  if ($status === 'trial')  return '<span class="badge text-bg-warning">TESTE</span>';
  return '<span class="badge text-bg-secondary">' . htmlspecialchars(strtoupper($status)) . '</span>';
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
  <title>Clientes</title>
</head>

<body class="bg-light">
  <div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h4 class="mb-0">Clientes</h4>
        <div class="text-muted">Lista de clientes (Teste / Pago)</div>
      </div>
      <div class="d-flex gap-2">
        <a class="btn btn-primary" href="/create_customer.php">Novo cliente</a>
        <a class="btn btn-outline-secondary" href="/index.php">Voltar</a>
      </div>
    </div>

    <div class="bg-white rounded-4 shadow-sm p-3">
      <div class="table-responsive">
        <table class="table align-middle">
          <thead>
            <tr>
              <th>Cliente</th>
              <th>WhatsApp</th>
              <th>Indicador</th>
              <th>Status</th>
              <th>Cadastrado em</th>
              <th class="text-end">Ações</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$customers): ?>
              <tr>
                <td colspan="6" class="text-muted">Nenhum cliente ainda.</td>
              </tr>
            <?php else: foreach ($customers as $c): ?>
              <?php
              $indicator = trim((string)($c['indicator_slug'] ?? ''));
              $indicatorLabel = ($indicator !== '') ? '@' . $indicator : '—';

              $waFrom   = trim((string)($c['wa_from'] ?? ''));      // número real (oficial)
              $waChatId = trim((string)($c['wa_chat_id'] ?? ''));   // pode ser número/identificador que você usa internamente
              $phone    = trim((string)($c['phone'] ?? ''));

              // Prioridade (sem LID): wa_from > wa_chat_id > phone
              $display = '';
              $waLink = '';

              $pick = '';
              if ($waFrom !== '') $pick = $waFrom;
              elseif ($waChatId !== '') $pick = $waChatId;
              elseif ($phone !== '') $pick = $phone;

              if ($pick !== '') {
                $norm = br_phone_normalize($pick);
                if ($norm['ok']) {
                  $display = $norm['display'];
                  $waLink = 'https://wa.me/' . $norm['e164'];
                } else {
                  // se não for número válido, mostra raw sem link
                  $display = $pick;
                }
              } else {
                $display = '—';
              }
              ?>
              <tr id="row-<?= htmlspecialchars($c['id']) ?>">
                <td>
                  <div class="fw-semibold"><?= htmlspecialchars($c['name'] ?? '-') ?></div>
                  <div class="text-muted small">ID: <?= htmlspecialchars(substr($c['id'], 0, 8)) ?>…</div>
                </td>

                <td>
                  <?php if ($display === '—'): ?>
                    <span class="text-muted">—</span>
                  <?php elseif ($waLink !== ''): ?>
                    <a class="text-decoration-none" target="_blank" href="<?= htmlspecialchars($waLink) ?>">
                      <i class="bi bi-whatsapp text-success me-1"></i><?= htmlspecialchars($display) ?>
                    </a>
                  <?php else: ?>
                    <span><?= htmlspecialchars($display) ?></span>
                  <?php endif; ?>
                </td>

                <td>
                  <div class="fw-semibold"><?= htmlspecialchars($indicatorLabel) ?></div>
                </td>

                <td id="st-<?= htmlspecialchars($c['id']) ?>">
                  <?= customer_status_badge($c['status'] ?? 'trial') ?>
                </td>

                <td class="text-muted">
                  <?= htmlspecialchars(fmt_br_datetime($c['created_at'] ?? null)) ?>
                </td>

                <td class="text-end">
                  <div class="d-flex justify-content-end gap-2 flex-wrap">
                    <?php if (($c['status'] ?? 'trial') !== 'active'): ?>
                      <button class="btn btn-sm btn-success"
                        onclick="markCustomerPaid('<?= htmlspecialchars($c['id']) ?>')"
                        id="btn-pay-<?= htmlspecialchars($c['id']) ?>">
                        Marcar pago
                      </button>
                    <?php else: ?>
                      <span class="text-muted small">—</span>
                    <?php endif; ?>

                    <a class="btn btn-sm btn-outline-primary" href="/edit_customer.php?id=<?= urlencode($c['id']) ?>">
                      Editar
                    </a>

                    <button class="btn btn-sm btn-outline-danger"
                      onclick="openDeleteCustomerModal('<?= htmlspecialchars($c['id']) ?>','<?= htmlspecialchars(addslashes($c['name'] ?? '')) ?>')">
                      Excluir
                    </button>
                  </div>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>

  <!-- Modal Excluir Cliente -->
  <div class="modal fade" id="deleteCustomerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Excluir cliente</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>

        <div class="modal-body">
          <p class="mb-2">
            Você está prestes a excluir o cliente:
            <b id="delCustomerName">—</b>
          </p>

          <div class="alert alert-warning py-2">
            Essa ação é <b>irreversível</b>.
          </div>

          <label class="form-label">Digite <b>EXCLUIR</b> para confirmar</label>
          <input id="delCustomerConfirmText"
            class="form-control"
            placeholder="EXCLUIR"
            autocomplete="off"
            oninput="onDeleteCustomerInput()">
          <input type="hidden" id="delCustomerId">
          <div class="text-danger small mt-2 d-none" id="delCustomerError"></div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="button"
            class="btn btn-danger"
            id="btnConfirmDeleteCustomer"
            onclick="confirmDeleteCustomer()"
            disabled>
            Excluir
          </button>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <script>
    async function markCustomerPaid(customerId) {
      const btn = document.getElementById('btn-pay-' + customerId);
      if (btn) {
        btn.disabled = true;
        btn.innerText = 'Processando...';
      }

      const form = new FormData();
      form.append('customer_id', customerId);

      const resp = await fetch('/mark_customer_paid.php', {
        method: 'POST',
        body: form
      });
      const data = await resp.json();

      if (!data.ok) {
        alert(data.error || 'Erro ao marcar como pago');
        if (btn) {
          btn.disabled = false;
          btn.innerText = 'Marcar pago';
        }
        return;
      }

      const st = document.getElementById('st-' + customerId);
      st.innerHTML = '<span class="badge text-bg-success">PAGO</span>';

      if (btn) btn.remove();

      if (data.commission?.ok && !data.commission?.skipped) {
        console.log('Pagamento marcado. Comissão gerada:', data.commission);
      } else {
        console.log('Pagamento marcado sem comissão.');
      }
    }

    let deleteCustomerModal;

    function onDeleteCustomerInput() {
      const typed = (document.getElementById('delCustomerConfirmText').value || '').trim().toLowerCase();
      const btn = document.getElementById('btnConfirmDeleteCustomer');
      btn.disabled = (typed !== 'excluir');
    }

    function openDeleteCustomerModal(customerId, customerName) {
      const el = document.getElementById('deleteCustomerModal');
      deleteCustomerModal = bootstrap.Modal.getOrCreateInstance(el);

      document.getElementById('delCustomerId').value = customerId;
      document.getElementById('delCustomerName').innerText = customerName || '—';
      document.getElementById('delCustomerConfirmText').value = '';
      document.getElementById('delCustomerError').classList.add('d-none');
      document.getElementById('delCustomerError').innerText = '';

      const btn = document.getElementById('btnConfirmDeleteCustomer');
      btn.disabled = true;
      btn.innerText = 'Excluir';

      deleteCustomerModal.show();
      setTimeout(() => document.getElementById('delCustomerConfirmText').focus(), 150);
    }

    async function confirmDeleteCustomer() {
      const customerId = document.getElementById('delCustomerId').value;
      const typed = (document.getElementById('delCustomerConfirmText').value || '').trim();
      const err = document.getElementById('delCustomerError');
      const btn = document.getElementById('btnConfirmDeleteCustomer');

      if (typed.toLowerCase() !== 'excluir') {
        err.innerText = 'Digite EXCLUIR para confirmar.';
        err.classList.remove('d-none');
        return;
      }

      btn.disabled = true;
      btn.innerText = 'Excluindo...';

      const form = new FormData();
      form.append('customer_id', customerId);

      const resp = await fetch('/delete_customer.php', {
        method: 'POST',
        body: form
      });
      const data = await resp.json();

      if (!data.ok) {
        err.innerText = data.error || 'Erro ao excluir.';
        err.classList.remove('d-none');
        btn.disabled = false;
        btn.innerText = 'Excluir';
        return;
      }

      const row = document.getElementById('row-' + customerId);
      if (row) row.remove();

      deleteCustomerModal.hide();
    }
  </script>
</body>

</html>