<?php
require __DIR__ . '/_guard.php';
require __DIR__ . '/../../src/supabase.php';

header('Content-Type: application/json');

$id = $_POST['customer_id'] ?? '';
if (!$id) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'missing customer_id']); exit; }

try {
  // Se existir subscription/pagamentos, ideal é deletar em cascata (ou bloquear)
  // Aqui vamos apenas deletar o customer.
  sb_request('DELETE', "customers?id=eq." . urlencode($id), null, true);

  echo json_encode(['ok'=>true]);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}