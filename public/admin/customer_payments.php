<?php
require __DIR__ . '/_guard.php';
require __DIR__ . '/../../src/supabase.php';

$customerId = $_GET['customer_id'] ?? '';
if (!$customerId) { http_response_code(400); echo "missing customer_id"; exit; }

try {
  // Dados do cliente + afiliado
  $cust = sb_request('GET',
    "customers?select=id,name,phone,created_at,affiliate_id,affiliates(name,code)&id=eq.$customerId&limit=1",
    null, true
  );
  if (!$cust) throw new Exception("cliente não encontrado");
  $cust = $cust[0];

  // assinatura
  $subs = sb_request('GET',
    "subscriptions?select=id,status,monthly_price,started_at&customer_id=eq.$customerId&limit=1",
    null, true
  );
  if (!$subs) throw new Exception("assinatura não encontrada");
  $sub = $subs[0];

  // pagamentos
  $payments = sb_request('GET',
    "payments?select=id,reference_month,amount,status,paid_at,created_at&subscription_id=eq.{$sub['id']}&order=reference_month.desc",
    null, true
  );

} catch (Exception $e) {
  http_response_code(404); echo $e->getMessage(); exit;
}
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="/assets/css/global.css" rel="stylesheet">
  <title>Pagamentos do cliente</title>
</head>
<body class="bg-light">
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-start mb-3">
    <div>
      <h4 class="mb-1">Pagamentos</h4>
      <div class="text-muted">
        Cliente: <b><?= htmlspecialchars($cust['name'] ?? '-') ?></b> • <?= htmlspecialchars($cust['phone']) ?><br>
        Afiliado: <b><?= htmlspecialchars($cust['affiliates']['name'] ?? '-') ?></b>
        (<?= htmlspecialchars($cust['affiliates']['code'] ?? '-') ?>)
      </div>
    </div>
    <div class="d-flex gap-2">
      <a class="btn btn-outline-secondary" href="/customers.php">Voltar</a>
    </div>
  </div>

  <div class="bg-white rounded-4 shadow-sm p-3 mb-3">
    <form class="row g-2 align-items-end" method="post" action="/create_payment_for_subscription.php">
      <input type="hidden" name="subscription_id" value="<?= htmlspecialchars($sub['id']) ?>">
      <div class="col-md-4">
        <label class="form-label">Mês (referência)</label>
        <input class="form-control" type="month" name="month" required>
        <small class="text-muted">Cria pagamento "unpaid" para este mês.</small>
      </div>
      <div class="col-md-3">
        <label class="form-label">Valor</label>
        <input class="form-control" name="amount" value="<?= htmlspecialchars(number_format((float)$sub['monthly_price'], 2, '.', '')) ?>" required>
      </div>
      <div class="col-md-3">
        <button class="btn btn-primary w-100">Criar pagamento</button>
      </div>
    </form>
  </div>

  <div class="bg-white rounded-4 shadow-sm p-3">
    <div class="table-responsive">
      <table class="table align-middle">
        <thead>
          <tr>
            <th>Mês</th>
            <th>Status</th>
            <th>Valor</th>
            <th>Pago em</th>
            <th class="text-end">Ações</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$payments): ?>
          <tr><td colspan="5" class="text-muted">Nenhum pagamento criado ainda.</td></tr>
        <?php else: foreach ($payments as $p): ?>
          <tr id="row-<?= htmlspecialchars($p['id']) ?>">
            <td><?= htmlspecialchars(substr($p['reference_month'], 0, 7)) ?></td>
            <td>
              <span class="badge <?= $p['status']==='paid'?'text-bg-success':'text-bg-secondary' ?>" id="status-<?= htmlspecialchars($p['id']) ?>">
                <?= $p['status']==='paid' ? 'PAGO' : 'NÃO PAGO' ?>
              </span>
            </td>
            <td>R$ <?= number_format((float)$p['amount'], 2, ',', '.') ?></td>
            <td id="paidat-<?= htmlspecialchars($p['id']) ?>">
              <?= $p['paid_at'] ? htmlspecialchars(fmt_br_datetime($p['paid_at'])
) : '-' ?>
            </td>
            <td class="text-end">
              <?php if ($p['status'] !== 'paid'): ?>
                <button class="btn btn-sm btn-success"
                        onclick="markPaid('<?= htmlspecialchars($p['id']) ?>')"
                        id="btn-<?= htmlspecialchars($p['id']) ?>">
                  Marcar pago
                </button>
              <?php else: ?>
                <span class="text-muted">—</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
async function markPaid(paymentId){
  const btn = document.getElementById('btn-' + paymentId);
  if (btn) { btn.disabled = true; btn.innerText = 'Processando...'; }

  const form = new FormData();
  form.append('payment_id', paymentId);

  const resp = await fetch('/mark_paid.php', { method: 'POST', body: form });
  const data = await resp.json();

  if (!data.ok){
    alert(data.error || 'Erro ao marcar como pago');
    if (btn) { btn.disabled = false; btn.innerText = 'Marcar pago'; }
    return;
  }

  // Atualiza UI
  const st = document.getElementById('status-' + paymentId);
  st.className = 'badge text-bg-success';
  st.innerText = 'PAGO';

  const paidAt = document.getElementById('paidat-' + paymentId);
  paidAt.innerText = new Date().toLocaleString('pt-BR');

  if (btn) btn.remove();

  // Mostra comissão gerada
  const c = data.commission?.commission;
  if (c && c.ok && !c.skipped){
    alert('Pagamento marcado como PAGO. Comissão gerada: R$ ' + (c.amount?.toFixed(2) ?? ''));
  } else {
    alert('Pagamento marcado como PAGO. (Sem comissão neste pagamento)');
  }
}
</script>
</body>
</html>