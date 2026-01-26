<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

ini_set('display_errors', '1');
error_reporting(E_ALL);

if (empty($_SESSION['agent_id'])) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'not_logged']); exit; }
if (($_SESSION['agent_role'] ?? 'agent') === 'admin') { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'admin_forbidden']); exit; }

$cfg = require __DIR__ . '/../app/config.php';
require __DIR__ . '/../app/supabase.php';
$sb = new Supabase($cfg);

$input = json_decode(file_get_contents('php://input'), true);
$ticketId = trim($input['ticket_id'] ?? $input['ticketId'] ?? $input['ticket'] ?? $input['id'] ?? '');
$text = trim((string)($input['text'] ?? $input['body'] ?? $input['message'] ?? $input['msg'] ?? ''));

if ($ticketId === '' || $text === '') {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'missing_fields']);
  exit;
}

// 1) ticket + customer
$r = $sb->request('GET', '/rest/v1/tickets', [
  'select' => 'id,customer_id,customers(*)',
  'id' => 'eq.' . $ticketId,
  'limit' => 1
]);

$t = $r['json'][0] ?? null;
if (!$t) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'ticket_not_found']); exit; }

$cust = $t['customers'] ?? [];
$customerId = $t['customer_id'] ?? ($cust['id'] ?? null);

$chatId = $cust['wa_chat_id'] ?? $cust['chat_id'] ?? $cust['whatsapp'] ?? '';

if (!$chatId && $customerId) {
  $rc = $sb->request('GET', '/rest/v1/customers', [
    'select' => 'id,wa_chat_id,chat_id,whatsapp',
    'id' => 'eq.' . $customerId,
    'limit' => 1
  ]);
  $c2 = $rc['json'][0] ?? null;
  if ($c2) {
    $chatId = $c2['wa_chat_id'] ?? $c2['chat_id'] ?? $c2['whatsapp'] ?? '';
  }
}

// 2) envia no WAHA
$wahaCandidates = [
  'http://waha:3000/api/sendText',   // nome do serviço no compose
  'http://127.0.0.1:3000/api/sendText' // fallback só se rodar fora de container
];

$payload = json_encode([
  'session' => 'default',
  'chatId'  => $chatId,
  'text'    => $text
], JSON_UNESCAPED_UNICODE);

$sentOk = false;
$lastHttp = null;
$lastResp = null;
$lastErr = null;
$usedUrl = null;

foreach ($wahaCandidates as $url) {
  $usedUrl = $url;
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_TIMEOUT => 20,
  ]);
  $resp = curl_exec($ch);
  $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err  = curl_error($ch);
  curl_close($ch);

  $lastHttp = $http;
  $lastResp = $resp;
  $lastErr  = $err ?: null;

  if ($resp !== false && $http >= 200 && $http < 500) {
    $sentOk = true;
    break;
  }
}

if (!$sentOk) {
  http_response_code(502);
  echo json_encode([
    'ok'=>false,
    'error'=>'waha_send_failed',
    'waha_url'=>$usedUrl,
    'http'=>$lastHttp,
    'curl_error'=>$lastErr,
    'response'=>$lastResp
  ]);
  exit;
}

// extrai id do waha se vier
$wahaId = null;
$decoded = json_decode($lastResp, true);
if (is_array($decoded)) {
  $wahaId = $decoded['id'] ?? $decoded['messageId'] ?? ($decoded['message']['id'] ?? null);
}

$nowIso = gmdate('c');

// 3) grava em public.messages (SUA estrutura) — SEM engolir erro
$ins = $sb->request('POST', '/rest/v1/messages', [], [
  'ticket_id' => $ticketId,
  'customer_id' => $customerId,
  'direction' => 'OUT',
  'text' => $text,
  'media_url' => null,
  'waha_message_id' => $wahaId,
  // Para “ordem de chegada” ficar perfeita:
  'sent_at' => $nowIso,
  'created_at' => $nowIso,
]);

// Se o seu Supabase class devolve erro em $ins, a gente para aqui
if (isset($ins['ok']) && $ins['ok'] === false) {
  http_response_code(500);
  echo json_encode([
    'ok'=>false,
    'error'=>'supabase_insert_failed',
    'detail'=>$ins['error'] ?? null,
    'status'=>$ins['status'] ?? null,
    'raw'=>$ins,
  ]);
  exit;
}

// 4) atualiza last_message_at do ticket
$sb->request('PATCH', '/rest/v1/tickets', ['id'=>'eq.' . $ticketId], [
  'last_message_at' => $nowIso
]);

echo json_encode([
  'ok'=>true,
  'sent'=>[
    'ticket_id'=>$ticketId,
    'customer_id'=>$customerId,
    'chatId'=>$chatId,
    'text'=>$text,
    'waha_message_id'=>$wahaId,
    'created_at'=>$nowIso,
  ]
]);

exit;