<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['agent_id'])) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'not_logged']); exit; }
if (($_SESSION['agent_role'] ?? 'agent') === 'admin') { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'admin_forbidden']); exit; }

$cfg = require __DIR__ . '/../app/config.php';
require __DIR__ . '/../app/supabase.php';
$sb = new Supabase($cfg);

$input = json_decode(file_get_contents('php://input'), true);
$ticketId = trim(($input['ticket_id'] ?? $input['ticketId'] ?? $input['ticket'] ?? ''));

if ($ticketId === '') { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'missing_ticket']); exit; }

try {
  $sb->request('PATCH', '/rest/v1/tickets', [
    'id' => 'eq.' . $ticketId,
    'select' => 'id'
  ], [
    'status' => 'DONE',
    'closed_at' => gmdate('c')
  ]);

  echo json_encode(['ok'=>true]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'php_fatal','message'=>$e->getMessage()]);
}