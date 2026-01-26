<?php
// Apague este arquivo depois de criar o primeiro agente!
$cfg = require __DIR__ . '/../app/config.php';
require __DIR__ . '/../app/supabase.php';
$sb = new Supabase($cfg);

$username = 'admin';
$password = 'admin123';
$name = 'Admin';

$hash = password_hash($password, PASSWORD_BCRYPT);

$r = $sb->request('POST', '/rest/v1/agents', [], [[
  'name' => $name,
  'username' => $username,
  'password_hash' => $hash,
  'is_active' => true
]]);

echo json_encode([
  'ok' => true,
  'created' => $r['json'] ?? null,
  'login' => ['username'=>$username,'password'=>$password],
  'note' => 'Apague /api/seed_agent.php depois.'
]);