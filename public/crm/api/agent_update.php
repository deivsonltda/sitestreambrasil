<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['agent_id'])) { http_response_code(401); echo json_encode(['ok'=>false]); exit; }
if (($_SESSION['agent_role'] ?? 'agent') !== 'admin') { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'admin_only']); exit; }

$cfg = require __DIR__ . '/../app/config.php';
require __DIR__ . '/../app/supabase.php';

$sb = new Supabase($cfg);

$input = json_decode(file_get_contents('php://input'), true);

$id = $input['id'] ?? null;
$name = trim($input['name'] ?? '');
$username = trim($input['username'] ?? '');
$password = (string)($input['password'] ?? '');
$is_active = $input['is_active'] ?? null; // bool
$role = $input['role'] ?? null; // opcional, se você quiser editar role

if (!$id) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'missing_id']); exit; }
if ($name === '' || $username === '') { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'missing_fields']); exit; }

$payload = [
  'name' => $name,
  'username' => $username,
];

// Atualizar ativo/inativo se veio
if (is_bool($is_active)) $payload['is_active'] = $is_active;

// (Opcional) Atualizar role
if ($role !== null) {
  if (!in_array($role, ['admin','agent'], true)) {
    http_response_code(400); echo json_encode(['ok'=>false,'error'=>'invalid_role']); exit;
  }
  $payload['role'] = $role;
}

// Senha: só atualiza se foi enviada e tem tamanho mínimo
if ($password !== '') {
  if (strlen($password) < 6) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'weak_password']); exit; }
  $payload['password_hash'] = password_hash($password, PASSWORD_BCRYPT);
}

$r = $sb->request('PATCH', '/rest/v1/agents', ['id' => 'eq.' . $id], $payload);

if (($r['code'] ?? 500) >= 400) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'update_failed','detail'=>$r['raw'] ?? null]);
  exit;
}

echo json_encode(['ok'=>true]);