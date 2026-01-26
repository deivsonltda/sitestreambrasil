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

if (!$id) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'missing_id']); exit; }

// Não deixar admin se auto-excluir
if ($id === ($_SESSION['agent_id'] ?? '')) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'cannot_delete_self']);
  exit;
}

// Buscar o agente alvo (pra saber se é admin)
$rTarget = $sb->request('GET', '/rest/v1/agents', [
  'select' => 'id,role',
  'id' => 'eq.' . $id,
  'limit' => 1
]);

$target = $rTarget['json'][0] ?? null;
if (!$target) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'not_found']); exit; }

// Se estiver deletando um admin, garantir que existe mais de 1 admin
if (($target['role'] ?? 'agent') === 'admin') {
  $rAdmins = $sb->request('GET', '/rest/v1/agents', [
    'select' => 'id',
    'role' => 'eq.admin',
    'is_active' => 'eq.true'
  ]);

  $admins = $rAdmins['json'] ?? [];
  if (count($admins) <= 1) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'cannot_delete_last_admin']);
    exit;
  }
}

// Deletar
$r = $sb->request('DELETE', '/rest/v1/agents', ['id' => 'eq.' . $id]);

if (($r['code'] ?? 500) >= 400) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'delete_failed','detail'=>$r['raw'] ?? null]);
  exit;
}

echo json_encode(['ok'=>true]);