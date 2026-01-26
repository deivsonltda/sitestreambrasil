<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['agent_id'])) { http_response_code(401); echo json_encode(['ok'=>false]); exit; }
if (($_SESSION['agent_role'] ?? 'agent') !== 'admin') { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'admin_only']); exit; }

$cfg = require __DIR__ . '/../app/config.php';
require __DIR__ . '/../app/supabase.php';
$sb = new Supabase($cfg);

$input = json_decode(file_get_contents('php://input'), true);
$name = trim($input['name'] ?? '');
$username = trim($input['username'] ?? '');
$password = (string)($input['password'] ?? '');
$role = $input['role'] ?? 'agent';

if ($name === '' || $username === '' || $password === '') {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'missing_fields']); exit;
}

if (strlen($password) < 6) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'weak_password']); exit;
}

if (!in_array($role, ['admin','agent'], true)) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'invalid_role']); exit;
}

$hash = password_hash($password, PASSWORD_BCRYPT);

$r = $sb->request('POST', '/rest/v1/agents', [], [[
  'name' => $name,
  'username' => $username,
  'password_hash' => $hash,
  'role' => $role,
  'is_active' => true
]]);

if (($r['code'] ?? 500) >= 400) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'cannot_create','detail'=>$r['raw'] ?? null]); exit;
}

echo json_encode(['ok'=>true,'agent'=>$r['json'][0] ?? null]);