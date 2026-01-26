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

$cfg = require __DIR__ . '/../app/config.php';
require __DIR__ . '/../app/supabase.php';
$sb = new Supabase($cfg);

$q = trim($_GET['q'] ?? '');

try {
  // ⚠️ Use o padrão do seu wrapper: ele devolve ['json'=>...]
  $r = $sb->request('GET', '/rest/v1/tickets', [
    'select' => 'id,priority,status,assigned_to,last_message_at,customers(*)',
    'status' => 'eq.OPEN',
    'assigned_to' => 'is.null',
    'order'  => 'last_message_at.asc',
    'limit'  => 200
  ]);

  // Se por algum motivo não vier no formato esperado
  if (!is_array($r)) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'bad_supabase_response','raw_type'=>gettype($r)]);
    exit;
  }

  // ✅ O seu wrapper usa 'json'
  $rows = $r['json'] ?? null;

  // Se não veio 'json', manda de volta o raw pra debug (sem ficar cego)
  if (!is_array($rows)) {
    http_response_code(500);
    echo json_encode([
      'ok'=>false,
      'error'=>'missing_json_key',
      'raw'=>$r
    ]);
    exit;
  }

  $human = [];
  $support = [];

  foreach ($rows as $t) {
    $cust = $t['customers'] ?? [];

    $name = $cust['name'] ?? $cust['full_name'] ?? $cust['nome'] ?? '';
    $wa   = $cust['wa_chat_id'] ?? $cust['chat_id'] ?? $cust['whatsapp'] ?? '';
    $av   = $cust['avatar_url'] ?? $cust['photo_url'] ?? null;

    if ($q !== '') {
      $hay = mb_strtolower(($name ?: '') . ' ' . ($wa ?: ''));
      if (mb_strpos($hay, mb_strtolower($q)) === false) continue;
    }

    $item = [
      'id' => $t['id'],
      'priority' => $t['priority'],
      'customer_name' => ($name !== '' ? $name : ($wa !== '' ? $wa : 'Cliente')),
      'wa_chat_id' => $wa,
      'avatar_url' => $av,
      'last_message' => '', // sem inventar
      'last_time' => !empty($t['last_message_at']) ? date('H:i', strtotime($t['last_message_at'])) : ''
    ];

    if (($t['priority'] ?? '') === 'HUMAN') $human[] = $item;
    else $support[] = $item;
  }

  echo json_encode(['ok'=>true,'human'=>$human,'support'=>$support]);

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