<?php
require __DIR__ . '/_guard.php';
require __DIR__ . '/../../src/supabase.php';

$subscriptionId = trim($_POST['subscription_id'] ?? '');
$month = trim($_POST['month'] ?? '');
$amount = (float)($_POST['amount'] ?? 19.90);

if (!$subscriptionId || !$month) {
  http_response_code(400);
  echo "missing fields";
  exit;
}

if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
  http_response_code(400);
  echo "Mês inválido.";
  exit;
}

// "2025-12" -> "2025-12-01"
$referenceMonth = $month . '-01';

try {
  // 1) cria ou pega existente
  $existing = sb_request(
    'GET',
    "payments?select=id"
      . "&subscription_id=eq." . urlencode($subscriptionId)
      . "&reference_month=eq." . urlencode($referenceMonth)
      . "&limit=1",
    null,
    true
  );

  if (!$existing) {
    sb_request('POST', 'payments', [
      'subscription_id' => $subscriptionId,
      'reference_month' => $referenceMonth, // DATE: YYYY-MM-01
      'amount' => $amount,
      'status' => 'unpaid'
    ], true);
  }

  // 2) buscar customer_id pra voltar pra tela certa
  $sub = sb_request(
    'GET',
    "subscriptions?select=id,customer_id"
      . "&id=eq." . urlencode($subscriptionId)
      . "&limit=1",
    null,
    true
  );

  if (!$sub) throw new Exception("Assinatura não encontrada para voltar.");

  $customerId = $sub[0]['customer_id'] ?? null;
  if (!$customerId) throw new Exception("Assinatura sem customer_id.");

  header("Location: /customer_payments.php?customer_id=" . urlencode($customerId));
  exit;

} catch (Exception $e) {
  http_response_code(500);
  echo $e->getMessage();
  exit;
}
