<?php
session_start();
if (empty($_SESSION['agent_id'])) { http_response_code(401); echo json_encode(['ok'=>false]); exit; }

$cfg = require __DIR__ . '/../app/config.php';
require __DIR__ . '/../app/supabase.php';
$sb = new Supabase($cfg);

$input = json_decode(file_get_contents('php://input'), true);
$customerId = $input['customer_id'] ?? null;
$step = $input['step'] ?? null;

$allowed = ['BOT','SUPPORT','HUMAN'];
if (!$customerId || !$step || !in_array($step, $allowed, true)) {
  http_response_code(400); echo json_encode(['ok'=>false,'error'=>'invalid']); exit;
}

$sb->request('PATCH', '/rest/v1/customers', ['id' => 'eq.' . $customerId], [
  'step' => $step,
  'updated_at' => (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DateTime::ATOM),
]);

echo json_encode(['ok'=>true]);