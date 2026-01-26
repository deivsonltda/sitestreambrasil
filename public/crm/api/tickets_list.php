<?php
session_start();
if (empty($_SESSION['agent_id'])) { http_response_code(401); echo json_encode(['ok'=>false]); exit; }
if (($_SESSION['agent_role'] ?? 'agent') === 'admin') { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'admin_not_allowed']); exit; }

$cfg = require __DIR__ . '/../app/config.php';
require __DIR__ . '/../app/supabase.php';
$sb = new Supabase($cfg);

function fetchTickets($sb, $priority) {
  // join simples via select com relacionamento (PostgREST)
  return $sb->request('GET', '/rest/v1/tickets', [
    'select' => 'id,priority,status,assigned_to,last_message_at,created_at,customers(id,wa_chat_id,name,avatar_url,step)',
    'status' => 'eq.OPEN',
    'priority' => 'eq.' . $priority,
    'order' => 'last_message_at.asc'
  ])['json'] ?? [];
}

echo json_encode([
  'ok' => true,
  'human' => fetchTickets($sb, 'HUMAN'),
  'support' => fetchTickets($sb, 'SUPPORT'),
]);