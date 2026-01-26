<?php
require __DIR__ . '/_guard.php';
require __DIR__ . '/../../src/supabase.php';
require __DIR__ . '/../../src/commission.php';

header('Content-Type: application/json');

$customerId = $_POST['customer_id'] ?? '';
if (!$customerId) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'missing customer_id']);
  exit;
}

try {

  /**
   * 0) BUSCA CUSTOMER (FUNDAMENTAL)
   */
  $cust = sb_request(
    'GET',
    'customers?select=id,affiliate_id,indicator_slug&limit=1&id=eq.' . urlencode($customerId),
    null,
    true
  );

  if (!$cust) {
    throw new Exception('customer_not_found');
  }

  $affiliateId = $cust[0]['affiliate_id'] ?? null;
// sem afiliado = cliente orgânico, segue o fluxo normalmente

  /**
   * 1) BUSCA OU CRIA SUBSCRIPTION
   */
  $sub = sb_request(
    'GET',
    'subscriptions?select=id,monthly_price,status&customer_id=eq.' . urlencode($customerId) . '&limit=1',
    null,
    true
  );

  if (!$sub) {
    $created = sb_request('POST', 'subscriptions', [
      'customer_id' => $customerId,
      'status' => 'active',
      'monthly_price' => 19.90,
      'started_at' => gmdate('c')
    ], true);

    $subId = $created[0]['id'];
    $monthly = (float)$created[0]['monthly_price'];
  } else {
    $subId = $sub[0]['id'];
    $monthly = (float)($sub[0]['monthly_price'] ?? 19.90);
  }

  /**
   * 2) REFERÊNCIA DO MÊS
   */
  $ref = gmdate('Y-m-01');

  /**
   * 3) BUSCA OU CRIA PAYMENT
   */
  $pay = sb_request(
    'GET',
    'payments?select=id,status&subscription_id=eq.' . urlencode($subId) .
      '&reference_month=eq.' . urlencode($ref) . '&limit=1',
    null,
    true
  );

  if (!$pay) {
    $pCreated = sb_request('POST', 'payments', [
      'subscription_id' => $subId,
      'reference_month' => $ref,
      'amount' => $monthly,
      'status' => 'unpaid'
    ], true);

    $paymentId = $pCreated[0]['id'];
  } else {
    $paymentId = $pay[0]['id'];
  }

  /**
   * 4) MARCA PAYMENT COMO PAID (SE AINDA NÃO ESTIVER)
   */
  sb_request(
    'PATCH',
    'payments?id=eq.' . urlencode($paymentId),
    [
      'status' => 'paid',
      'paid_at' => gmdate('c')
    ],
    true
  );

  /**
   * 5) MARCA CUSTOMER COMO ACTIVE
   */
  sb_request(
    'PATCH',
    'customers?id=eq.' . urlencode($customerId),
    [
      'status' => 'active'
    ],
    true
  );

  // ✅ garante affiliate_id correto antes de gerar comissão
  $cust = sb_request(
    'GET',
    "customers?select=id,affiliate_id,indicator_slug&limit=1&id=eq." . urlencode($customerId),
    null,
    true
  );

  if ($cust) {
    $custAff = (string)($cust[0]['affiliate_id'] ?? '');
    $slug    = strtolower(trim((string)($cust[0]['indicator_slug'] ?? '')));

    if ($slug !== '') {
      $a = sb_request(
        'GET',
        "affiliates?select=id,instagram_username&instagram_username=eq." . urlencode($slug) . "&limit=1",
        null,
        true
      );

      if ($a && isset($a[0]['id'])) {
        $rightAff = (string)$a[0]['id'];

        if ($rightAff !== '' && $rightAff !== $custAff) {
          sb_request('PATCH', "customers?id=eq." . urlencode($customerId), [
            'affiliate_id' => $rightAff
          ], true);
        }
      }
    }
  }

  /**
   * 6) GERA COMISSÃO (PROTEGIDO)
   */
  $commission = generate_commission_for_payment($paymentId);

  echo json_encode([
    'ok' => true,
    'customer_id' => $customerId,
    'affiliate_id' => $affiliateId,
    'payment_id' => $paymentId,
    'commission' => $commission
  ]);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode([
    'ok' => false,
    'error' => $e->getMessage()
  ]);
}
