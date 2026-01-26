<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['agent_id'])) {
  http_response_code(401);
  echo json_encode(['ok' => false, 'error' => 'not_logged']);
  exit;
}
if (($_SESSION['agent_role'] ?? 'agent') === 'admin') {
  http_response_code(403);
  echo json_encode(['ok' => false, 'error' => 'admin_forbidden']);
  exit;
}

$cfg = require __DIR__ . '/../app/config.php';
require __DIR__ . '/../app/supabase.php';
$sb = new Supabase($cfg);

$ticketId = trim($_GET['ticket'] ?? $_GET['ticket_id'] ?? $_GET['id'] ?? $_GET['t'] ?? '');
if ($ticketId === '') {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'missing_ticket']);
  exit;
}

$r = $sb->request('GET', '/rest/v1/tickets', [
  'select' => 'id,priority,status,assigned_to,customer_id,created_at,last_message_at,customers(*)',
  'id' => 'eq.' . $ticketId,
  'limit' => 1
]);

$t = $r['json'][0] ?? null;
if (!$t) {
  http_response_code(404);
  echo json_encode(['ok' => false, 'error' => 'ticket_not_found']);
  exit;
}

$cust = $t['customers'] ?? [];
$name = $cust['name'] ?? $cust['full_name'] ?? $cust['nome'] ?? '';
$wa   = $cust['wa_chat_id'] ?? $cust['chat_id'] ?? $cust['whatsapp'] ?? '';
$av   = $cust['avatar_url'] ?? $cust['photo_url'] ?? null;

$customerName = ($name !== '' ? $name : ($wa !== '' ? $wa : 'Cliente'));

$priority = $t['priority'] ?? '';
$status   = $t['status'] ?? '';

$last = $t['last_message_at'] ?? null;

// monta "sub" com data/hora quando existir
$sub = trim($priority . ' • ' . $status);
if ($last) {
  $ts = strtotime($last);
  if ($ts !== false) {
    // formato simples (dd/mm HH:ii)
    $sub .= ' • ' . date('d/m H:i', $ts);
  }
}

echo json_encode([
  'ok' => true,

  'ticket' => [
    'id' => $t['id'],

    'priority' => $priority,
    'status' => $status,

    'assigned_to' => $t['assigned_to'] ?? null,
    'customer_id' => $t['customer_id'] ?? null,

    // datas (aliases)
    'created_at' => $t['created_at'] ?? null,
    'createdAt'  => $t['created_at'] ?? null,
    'last_message_at' => $last,
    'lastMessageAt'   => $last,

    // chat id
    'wa_chat_id' => $wa,
    'chat_id'    => $wa,

    // nomes possíveis que o JS pode estar usando
    'customer_name' => $customerName,
    'customerName'  => $customerName,
    'name'          => $customerName,
    'title'         => $customerName,

    // avatar possíveis
    'avatar_url' => $av,
    'avatarUrl'  => $av,
    'avatar'     => $av,

    // subline (já com hora se tiver)
    'sub' => $sub,
  ],

  // compat extra (alguns JS esperam no root)
  'customer' => [
    'name' => $customerName,
    'wa_chat_id' => $wa,
    'chat_id' => $wa,
    'avatar_url' => $av,
    'avatarUrl' => $av,
    'avatar' => $av,
  ]
]);