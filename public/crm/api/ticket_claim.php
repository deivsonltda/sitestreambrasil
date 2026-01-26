<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

ini_set('display_errors', '1');
error_reporting(E_ALL);

if (empty($_SESSION['agent_id'])) {
  http_response_code(401);
  echo json_encode(['ok'=>false,'error'=>'not_logged']); exit;
}
if (($_SESSION['agent_role'] ?? 'agent') === 'admin') {
  http_response_code(403);
  echo json_encode(['ok'=>false,'error'=>'admin_forbidden']); exit;
}

$agentId = $_SESSION['agent_id'];

$cfg = require __DIR__ . '/../app/config.php';
require __DIR__ . '/../app/supabase.php';
$sb = new Supabase($cfg);

$input = json_decode(file_get_contents('php://input'), true);
$ticketId = trim($input['ticket_id'] ?? '');

if ($ticketId === '') {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'missing_ticket_id']); exit;
}

try {
  // UPDATE com trava de corrida:
  // só atualiza se status=OPEN e assigned_to IS NULL
  $body = [
    'status' => 'IN_PROGRESS',
    'assigned_to' => $agentId,
    'last_message_at' => gmdate('c'),
  ];

  $r = $sb->request('PATCH', '/rest/v1/tickets', [
    'id' => 'eq.' . $ticketId,
    'status' => 'eq.OPEN',
    'assigned_to' => 'is.null',
    'select' => 'id'
  ], $body);

  // Seu wrapper retorna sempre em ['json'] quando OK.
  // Se não vier json, devolve raw pra debug
  if (!is_array($r)) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'bad_supabase_response','raw_type'=>gettype($r)]); exit;
  }

  $changed = $r['json'][0]['id'] ?? null;

  // Se não mudou nenhuma linha: alguém já pegou OU RLS bloqueou e veio vazio
  if (!$changed) {
    // Se existir algum campo de erro no retorno, mostramos
    // (como não sabemos seu wrapper, devolvemos tudo)
    http_response_code(409);
    echo json_encode([
      'ok'=>false,
      'error'=>'claim_failed',
      'hint'=>'Ou outro atendente já pegou, ou RLS bloqueou o update.',
      'raw'=>$r
    ]);
    exit;
  }

  echo json_encode(['ok'=>true,'ticket_id'=>$changed]);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([
    'ok'=>false,
    'error'=>'php_fatal',
    'message'=>$e->getMessage(),
    'file'=>$e->getFile(),
    'line'=>$e->getLine()
  ]);
}