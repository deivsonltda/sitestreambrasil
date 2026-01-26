<?php
session_start();
if (empty($_SESSION['agent_id'])) { http_response_code(401); echo json_encode(['ok'=>false]); exit; }

$cfg = require __DIR__ . '/../app/config.php';
require __DIR__ . '/../app/supabase.php';
$sb = new Supabase($cfg);

$r = $sb->request('GET', '/rest/v1/tickets', [
  'select' => 'id,priority,status,assigned_to,last_message_at,created_at,customers(id,wa_chat_id,name,avatar_url,step)',
  'status' => 'eq.IN_PROGRESS',
  'order' => 'last_message_at.desc'
]);

echo json_encode(['ok'=>true,'tickets'=>$r['json'] ?? []]);