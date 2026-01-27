<?php
require __DIR__ . '/_guard.php';
require __DIR__ . '/../../src/supabase.php';
require __DIR__ . '/../../src/utils.php';

$cfg = require __DIR__ . '/../../config.php';

$affiliateId = $_SESSION['affiliate_id'] ?? null;
$affName     = $_SESSION['affiliate_name'] ?? '';
$code        = $_SESSION['affiliate_code'] ?? '';

if (!$affiliateId) {
  header("Location: /login.php");
  exit;
}

if (!$code) {
?>
  <!doctype html>
  <html lang="pt-br">

  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <title>Painel do Afiliado</title>
  </head>

  <body class="bg-light">
    <div class="container py-5" style="max-width:520px;">
      <div class="bg-white rounded-4 shadow-sm p-4">
        <h4>Painel do Afiliado</h4>
        <form method="get" class="mt-3">
          <label class="form-label">Seu código</label>
          <input class="form-control mb-3" name="code" placeholder="ex: lais-7H2K" required>
          <button class="btn btn-primary w-100">Entrar</button>
        </form>
      </div>
    </div>
  </body>

  </html>
<?php
  exit;
}

/**
 * Badge comparativo vs período anterior
 */
function render_delta_chip($current, $previous)
{
  $current  = (float)$current;
  $previous = (float)$previous;

  if ($previous <= 0) {
    if ($current <= 0) {
      return '<span class="delta-chip neutral"><i class="bi bi-dash"></i> 0%</span>';
    }
    return '<span class="delta-chip up"><i class="bi bi-arrow-up-right"></i> novo</span>';
  }

  $pct = (($current - $previous) / $previous) * 100;
  $pctLabel = number_format(abs($pct), 1, ',', '.') . '%';

  if (abs($pct) < 0.05) {
    return '<span class="delta-chip neutral"><i class="bi bi-dash"></i> 0%</span>';
  }

  if ($pct > 0) {
    return '<span class="delta-chip up"><i class="bi bi-arrow-up-right"></i> ' . $pctLabel . '</span>';
  }

  return '<span class="delta-chip down"><i class="bi bi-arrow-down-right"></i> ' . $pctLabel . '</span>';
}

/**
 * Retorna [prevStart, prevEnd] com o mesmo tamanho do período atual, terminando em $start.
 */
function get_previous_range(DateTime $start, DateTime $end)
{
  $startTs = $start->getTimestamp();
  $endTs   = $end->getTimestamp();
  $dur     = max(1, $endTs - $startTs); // segurança

  $prevEndTs   = $startTs;
  $prevStartTs = $prevEndTs - $dur;

  // mantém o timezone do $start
  $tz = $start->getTimezone();

  $prevStart = (new DateTime('@' . $prevStartTs))->setTimezone($tz);
  $prevEnd   = (new DateTime('@' . $prevEndTs))->setTimezone($tz);

  return [$prevStart, $prevEnd];
}

try {
  $aff = sb_request(
    'GET',
    "affiliates?select=id,name,code,city,state&id=eq." . urlencode($affiliateId) . "&limit=1",
    null,
    true
  );
  if (!$aff) throw new Exception("Afiliado não encontrado.");
  $aff = $aff[0];

  // opcional: atualizar o code/nome na sessão se quiser:
  $_SESSION['affiliate_code'] = $aff['code'] ?? $_SESSION['affiliate_code'];
  $_SESSION['affiliate_name'] = $aff['name'] ?? $_SESSION['affiliate_name'];

  // período atual
  $preset = $_GET['period'] ?? 'today';
  $customStart = $_GET['start'] ?? null;
  $customEnd   = $_GET['end'] ?? null;
  [$start, $end] = get_period_range($preset, $customStart, $customEnd);

  // período anterior equivalente
  [$prevStart, $prevEnd] = get_previous_range($start, $end);

  // comissões período atual
  $comms = sb_request(
    'GET',
    "commissions?select=amount,rule_type,reference_month,created_at"
      . "&affiliate_id=eq." . urlencode($affiliateId)
      . "&created_at=gte." . urlencode($start->format('c'))
      . "&created_at=lt." . urlencode($end->format('c'))
      . "&order=created_at.desc",
    null,
    true
  );

  $total = 0.0;
  $adh = 0.0;
  $rec = 0.0;
  foreach ($comms as $c) {
    $total += (float)$c['amount'];
    if ($c['rule_type'] === 'adhesion_fixed') $adh += (float)$c['amount'];
    if ($c['rule_type'] === 'recurring_percent') $rec += (float)$c['amount'];
  }

  // comissões período anterior
  $commsPrev = sb_request(
    'GET',
    "commissions?select=amount,rule_type,created_at"
      . "&affiliate_id=eq." . urlencode($affiliateId)
      . "&created_at=gte." . urlencode($prevStart->format('c'))
      . "&created_at=lt." . urlencode($prevEnd->format('c')),
    null,
    true
  );

  $totalPrev = 0.0;
  $adhPrev = 0.0;
  $recPrev = 0.0;
  foreach ($commsPrev as $c) {
    $totalPrev += (float)$c['amount'];
    if ($c['rule_type'] === 'adhesion_fixed') $adhPrev += (float)$c['amount'];
    if ($c['rule_type'] === 'recurring_percent') $recPrev += (float)$c['amount'];
  }

  $refLink = rtrim($cfg['BASE_URL'] ?? '', '/') . '/a/' . $aff['code'];
} catch (Exception $e) {
  http_response_code(404);
  echo $e->getMessage();
  exit;
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
  <title>Painel - <?= htmlspecialchars($aff['name']) ?></title>

  <style>
    :root {
      /* Paleta (meio-termo): roxo protagonista + azuis sutis */
      --ink: #161433;
      --muted: rgba(22, 20, 51, .62);
      --bg: #f6f7fb;

      --violet: #7c5cff;
      --violet2: #5e7bff;
      --violet3: #8c78ff;

      --stroke: rgba(140, 120, 255, .20);
      --cardShadow: 0 14px 40px rgba(20, 18, 60, .08);

      --softViolet: rgba(124, 92, 255, .10);
      --softViolet2: rgba(94, 123, 255, .10);
    }

    html,
    body {
      height: 100%;
    }

    body.bg-light {
      min-height: 100dvh;
      /* dvh resolve barra dinâmica no mobile */
      background: var(--bg) !important;
      position: relative;
      overflow-x: hidden;
    }

    /* BACKGROUND REAL (não quebra em mobile) */
    body.bg-light::before {
      content: "";
      position: fixed;
      inset: -20%;
      /* maior que a tela para nunca “emendar” */
      z-index: -1;

      background:
        radial-gradient(900px 520px at 12% 10%, rgba(124, 92, 255, .16), transparent 60%),
        radial-gradient(900px 520px at 85% 20%, rgba(94, 123, 255, .14), transparent 60%),
        var(--bg);

      background-repeat: no-repeat;
      background-size: cover;
    }

    /* Botões (tudo puxado pro roxo) */
    .btn-primary {
      border: none;
      font-weight: 700;
      background: linear-gradient(135deg, var(--violet), var(--violet2));
      box-shadow: 0 14px 32px rgba(124, 92, 255, .22);
    }

    .btn-primary:hover {
      filter: brightness(1.03);
    }

    .btn-outline-primary {
      --bs-btn-color: var(--violet);
      --bs-btn-border-color: rgba(124, 92, 255, .40);
      --bs-btn-hover-bg: rgba(124, 92, 255, .10);
      --bs-btn-hover-border-color: rgba(124, 92, 255, .55);
      --bs-btn-hover-color: var(--ink);
      --bs-btn-active-bg: rgba(124, 92, 255, .14);
      --bs-btn-active-border-color: rgba(124, 92, 255, .65);
    }

    .btn-outline-secondary {
      --bs-btn-color: rgba(22, 20, 51, .72);
      --bs-btn-border-color: rgba(140, 120, 255, .28);
      --bs-btn-hover-bg: rgba(140, 120, 255, .10);
      --bs-btn-hover-border-color: rgba(140, 120, 255, .45);
      --bs-btn-hover-color: var(--ink);
    }

    /* Refresh */
    .btn-refresh i {
      transition: transform .4s ease;
    }

    .btn-refresh:active i {
      transform: rotate(360deg);
    }

    /* Cards */
    .card-soft,
    .card-kpi {
      border: 1px solid var(--stroke);
      border-radius: 18px;
      background: rgba(255, 255, 255, .92);
      box-shadow: var(--cardShadow);
      backdrop-filter: blur(6px);
    }

    /* KPI */
    .card-kpi .kpi-label {
      font-size: .9rem;
      color: rgba(22, 20, 51, .60);
    }

    .card-kpi .kpi-value {
      font-size: 1.72rem;
      font-weight: 800;
      letter-spacing: -.4px;
    }

    /* Header chip */
    .chip {
      display: inline-flex;
      align-items: center;
      gap: .4rem;
      padding: .36rem .62rem;
      border-radius: 999px;
      background: linear-gradient(135deg, rgba(124, 92, 255, .14), rgba(94, 123, 255, .12));
      border: 1px solid rgba(140, 120, 255, .22);
      color: rgba(22, 20, 51, .82);
      font-size: .85rem;
      font-weight: 600;
    }

    .chip i {
      color: rgba(124, 92, 255, .95);
    }

    /* Delta chip (como seu print) */
    .delta-chip {
      display: inline-flex;
      align-items: center;
      gap: .35rem;
      padding: .18rem .55rem;
      border-radius: 999px;
      font-size: .82rem;
      font-weight: 700;
      line-height: 1;
      border: 1px solid transparent;
      user-select: none;
    }

    .delta-chip i {
      font-size: .92rem;
    }

    .delta-chip.up {
      color: #15803d;
      background: rgba(34, 197, 94, .14);
      border-color: rgba(34, 197, 94, .22);
    }

    .delta-chip.down {
      color: #b91c1c;
      background: rgba(239, 68, 68, .14);
      border-color: rgba(239, 68, 68, .22);
    }

    .delta-chip.neutral {
      color: #475569;
      background: rgba(148, 163, 184, .14);
      border-color: rgba(148, 163, 184, .22);
    }

    /* Filtro */
    .form-label {
      font-weight: 700;
      color: rgba(22, 20, 51, .78);
      margin-bottom: 6px;
    }

    .form-select,
    .form-control {
      border-radius: 14px;
      border: 1px solid rgba(140, 120, 255, .22);
      background: rgba(255, 255, 255, .92);
      transition: .15s ease;
    }

    .form-select:focus,
    .form-control:focus {
      border-color: rgba(124, 92, 255, .55);
      box-shadow: 0 0 0 .2rem rgba(124, 92, 255, .16);
    }

    /* Input do link */
    #refLink {
      font-weight: 600;
      color: rgba(22, 20, 51, .78);
      background: linear-gradient(135deg, rgba(124, 92, 255, .06), rgba(94, 123, 255, .05));
    }

    .input-group .btn {
      border-radius: 14px;
    }

    .input-group .form-control {
      border-radius: 14px;
    }

    .input-group>:not(:first-child) {
      margin-left: 8px;
      border-radius: 14px !important;
    }

    .input-group>:not(:last-child) {
      border-radius: 14px !important;
    }

    /* Tabela com acento roxo */
    .table-soft {
      --rowHover: rgba(124, 92, 255, .06);
    }

    .table-soft thead th {
      color: rgba(70, 60, 140, .95);
      font-weight: 800;
      border-bottom: 1px solid rgba(140, 120, 255, .20);
      padding-top: 12px;
      padding-bottom: 12px;
    }

    .table-soft tbody td {
      padding-top: 12px;
      padding-bottom: 12px;
      border-color: rgba(140, 120, 255, .12);
    }

    .table-soft tbody tr:hover {
      background: var(--rowHover);
    }

    .type-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      font-size: 12px;
      padding: 7px 10px;
      border-radius: 999px;
      border: 1px solid transparent;
      font-weight: 700;
      letter-spacing: .2px;
      white-space: nowrap;
    }

    .type-adh {
      background: rgba(94, 123, 255, .12);
      border-color: rgba(94, 123, 255, .22);
      color: rgba(35, 90, 165, .95);
    }

    .type-rec {
      background: rgba(124, 92, 255, .12);
      border-color: rgba(124, 92, 255, .22);
      color: rgba(70, 60, 140, .95);
    }

    /* Ajustes responsivos */
    @media (max-width: 576px) {
      .card-kpi .kpi-value {
        font-size: 1.55rem;
      }

      .chip {
        font-size: .82rem;
      }
    }
  </style>
</head>

<body class="bg-light">
  <div class="container py-4">

    <!-- Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start gap-3 mb-3">
      <div>
        <h4 class="mb-1"><?= htmlspecialchars($aff['name']) ?></h4>
        <div class="text-muted d-flex flex-wrap gap-2 align-items-center">
          <span><?= htmlspecialchars($aff['city']) ?>/<?= htmlspecialchars($aff['state']) ?></span>
          <span class="chip"><i class="bi bi-at"></i> <?= htmlspecialchars($aff['code']) ?></span>
        </div>
      </div>

      <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary btn-refresh"
          href="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>"
          title="Atualizar painel">
          <i class="bi bi-arrow-clockwise"></i>
        </a>
        <a class="btn btn-outline-danger" href="/logout.php">Sair</a>
      </div>
    </div>

    <!-- KPIs + Link -->
    <div class="row g-3 mb-3">

      <div class="col-12 col-md-6 col-lg-3">
        <div class="p-3 card-kpi h-100">
          <div class="d-flex justify-content-between align-items-start">
            <div class="kpi-label">Total no período</div>
            <?= render_delta_chip($total, $totalPrev) ?>
          </div>
          <div class="kpi-value mt-1">R$ <?= number_format($total, 2, ',', '.') ?></div>
        </div>
      </div>

      <div class="col-12 col-md-6 col-lg-3">
        <div class="p-3 card-kpi h-100">
          <div class="d-flex justify-content-between align-items-start">
            <div class="kpi-label">Adesões</div>
            <?= render_delta_chip($adh, $adhPrev) ?>
          </div>
          <div class="kpi-value mt-1">R$ <?= number_format($adh, 2, ',', '.') ?></div>
        </div>
      </div>

      <div class="col-12 col-md-6 col-lg-3">
        <div class="p-3 card-kpi h-100">
          <div class="d-flex justify-content-between align-items-start">
            <div class="kpi-label">Recorrência</div>
            <?= render_delta_chip($rec, $recPrev) ?>
          </div>
          <div class="kpi-value mt-1">R$ <?= number_format($rec, 2, ',', '.') ?></div>
        </div>
      </div>

      <!-- Link de indicação + copiar -->
      <div class="col-12 col-md-6 col-lg-3">
        <div class="p-3 card-kpi h-100">
          <div class="d-flex justify-content-between align-items-start">
            <div class="kpi-label">Link de indicação</div>
          </div>

          <div class="mt-2">
            <div class="input-group">
              <input id="refLink" class="form-control" value="<?= htmlspecialchars($refLink) ?>" readonly>
              <button class="btn btn-outline-primary" type="button" onclick="copyRefLink()">
                <i class="bi bi-copy me-1"></i>Copiar
              </button>
            </div>
          </div>

        </div>
      </div>

    </div>

    <!-- Filtro -->
    <div class="p-3 card-kpi mb-3">
      <form class="row g-2 align-items-end" method="get">
        <input type="hidden" name="code" value="<?= htmlspecialchars($code) ?>">

        <div class="col-12 col-md-4">
          <label class="form-label">Período</label>
          <select class="form-select" name="period" id="period">
            <option value="today" <?= $preset === 'today' ? 'selected' : '' ?>>Hoje</option>
            <option value="yesterday" <?= $preset === 'yesterday' ? 'selected' : '' ?>>Ontem</option>
            <option value="last_7_days" <?= $preset === 'last_7_days' ? 'selected' : '' ?>>Últimos 7 dias</option>
            <option value="last_30_days" <?= $preset === 'last_30_days' ? 'selected' : '' ?>>Últimos 30 dias</option>
            <option value="custom" <?= $preset === 'custom' ? 'selected' : '' ?>>Personalizado</option>
          </select>
        </div>

        <div class="col-12 col-md-3" id="custom_start_wrap" style="display:none;">
          <label class="form-label">De</label>
          <input type="date" class="form-control" name="start" value="<?= htmlspecialchars($customStart ?? '') ?>">
        </div>

        <div class="col-12 col-md-3" id="custom_end_wrap" style="display:none;">
          <label class="form-label">Até</label>
          <input type="date" class="form-control" name="end" value="<?= htmlspecialchars($customEnd ?? '') ?>">
        </div>

        <div class="col-12 col-md-2">
          <button class="btn btn-primary w-100">Aplicar</button>
        </div>
      </form>
    </div>

    <!-- Tabela -->
    <div class="card-soft p-3">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <h6 class="mb-0" style="font-weight:800;color:rgba(70,60,140,.98);">Comissões</h6>
      </div>

      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0 table-soft">
          <thead>
            <tr>
              <th>Tipo</th>
              <th>Valor</th>
              <th class="text-end">Gerado em</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$comms): ?>
              <tr>
                <td colspan="3" class="text-muted py-3">Nenhuma comissão no período.</td>
              </tr>
              <?php else: foreach ($comms as $c): ?>
                <tr>
                  <td>
                    <span class="type-badge <?= $c['rule_type'] === 'adhesion_fixed' ? 'type-adh' : 'type-rec' ?>">
                      <?= $c['rule_type'] === 'adhesion_fixed' ? 'Adesão' : 'Recorrente' ?>
                    </span>
                  </td>

                  <td class="fw-semibold">
                    R$ <?= number_format((float)$c['amount'], 2, ',', '.') ?>
                  </td>

                  <td class="text-end text-muted">
                    <?= htmlspecialchars(fmt_br_datetime($c['created_at'])) ?>
                  </td>
                </tr>
            <?php endforeach;
            endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>

  <script>
    // auto refresh (não atrapalha custom)
    setInterval(() => {
      const period = document.getElementById('period');
      if (document.visibilityState !== 'visible') return;
      if (period && period.value === 'custom') return;
      window.location.reload();
    }, 60000);

    function syncCustomDates() {
      const period = document.getElementById('period');
      const show = period && period.value === 'custom';
      document.getElementById('custom_start_wrap').style.display = show ? 'block' : 'none';
      document.getElementById('custom_end_wrap').style.display = show ? 'block' : 'none';
    }
    document.getElementById('period')?.addEventListener('change', syncCustomDates);
    syncCustomDates();

    async function copyRefLink() {
      const input = document.getElementById('refLink');
      const text = input.value;

      try {
        await navigator.clipboard.writeText(text);
      } catch (e) {
        input.focus();
        input.select();
        document.execCommand('copy');
        input.blur();
      }
    }
  </script>
</body>

</html>