<?php
// Rode UMA VEZ para criar o primeiro admin. Depois APAGUE este arquivo.

$cfg = require __DIR__ . '/../app/config.php';
require __DIR__ . '/../app/supabase.php';
require __DIR__ . '/../app/helpers.php';

$sb = new Supabase($cfg);

$input = json_decode(file_get_contents('php://input'), true);
$name = trim($input['name'] ?? 'Admin');
$username = trim($input['username'] ?? '');
$password = $input['password'] ?? '';

if ($username === '' || $password === '') {
  json_out(['ok'=>false,'error'=>'missing_fields'], 400);
}

if (strlen($password) < 6) {
  json_out(['ok'=>false,'error'=>'weak_password'], 400);
}

// Verifica se já existe algum agente
$rCount = $sb->request('GET', '/rest/v1/agents', [
  'select' => 'id',
  'limit' => 1
]);

if (!empty($rCount['json'])) {
  json_out(['ok'=>false,'error'=>'already_initialized','message'=>'Já existe atendente. Use o painel/logado.'], 409);
}

// Cria o primeiro admin
$hash = password_hash($password, PASSWORD_BCRYPT);

$r = $sb->request('POST', '/rest/v1/agents', [], [[
  'name' => $name,
  'username' => $username,
  'password_hash' => $hash,
  'is_active' => true
]]);

if ($r['code'] >= 400) {
  json_out(['ok'=>false,'error'=>'cannot_create','detail'=>$r['raw']], 400);
}

json_out(['ok'=>true,'created'=>$r['json'][0] ?? null, 'note'=>'APAGUE /api/bootstrap_admin.php agora.']);