<?php
require __DIR__ . '/_guard.php';
require __DIR__ . '/../../src/supabase.php';
require __DIR__ . '/../../src/commission.php';

header('Content-Type: application/json');

$paymentId = $_POST['payment_id'] ?? '';
if (!$paymentId) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'missing payment_id']); exit; }

try {
  // Marca como pago (idempotente)
  sb_request('PATCH', "payments?id=eq.$paymentId", [
    'status' => 'paid',
    'paid_at' => gmdate('c')
  ], true);

  $res = generate_commission_for_payment($paymentId);

  echo json_encode([
    'ok' => true,
    'payment_id' => $paymentId,
    'commission' => $res
  ]);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}