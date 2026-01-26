<?php
session_start();

header('Content-Type: application/json; charset=utf-8');

$cfg = require __DIR__ . '/../app/config.php';
require __DIR__ . '/../app/supabase.php';

$sb = new Supabase($cfg);

$input = json_decode(file_get_contents('php://input'), true);
$user = trim($input['username'] ?? '');
$pass = (string)($input['password'] ?? '');

if ($user === '' || $pass === '') {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'missing_credentials']);
  exit;
}

$r = $sb->request('GET', '/rest/v1/agents', [
  'select' => 'id,name,username,role,password_hash,is_active',
  'username' => 'eq.' . $user,
  'is_active' => 'eq.true',
  'limit' => 1
]);

// Se Supabase retornar erro
if (($r['code'] ?? 500) >= 400 || !is_array($r['json'])) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'supabase_error','detail'=>$r['raw'] ?? null]);
  exit;
}

$agent = $r['json'][0] ?? null;
if (!$agent || empty($agent['password_hash']) || !password_verify($pass, $agent['password_hash'])) {
  http_response_code(401);
  echo json_encode(['ok'=>false,'error'=>'invalid_login']);
  exit;
}

// Protege contra session fixation
session_regenerate_id(true);

$_SESSION['agent_id'] = $agent['id'];
$_SESSION['agent_name'] = $agent['name'];
$_SESSION['agent_role'] = $agent['role'] ?? 'agent';
$_SESSION['agent_username'] = $agent['username'] ?? $user;

echo json_encode([
  'ok'=>true,
  'agent'=>[
    'id'=>$agent['id'],
    'name'=>$agent['name'],
    'role'=>$_SESSION['agent_role'],
    'username'=>$_SESSION['agent_username'],
  ]
]);