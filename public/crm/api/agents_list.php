<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['agent_id'])) { http_response_code(401); echo json_encode(['ok'=>false]); exit; }
if (($_SESSION['agent_role'] ?? 'agent') !== 'admin') { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'admin_only']); exit; }

$cfg = require __DIR__ . '/../app/config.php';
require __DIR__ . '/../app/supabase.php';
$sb = new Supabase($cfg);

$r = $sb->request('GET', '/rest/v1/agents', [
  'select' => 'id,name,username,role,is_active,created_at',
  'order' => 'created_at.desc'
]);

echo json_encode(['ok'=>true,'agents'=>$r['json'] ?? []]);