<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['agent_id'])) {
  http_response_code(401);
  echo json_encode(['ok'=>false,'error'=>'not_logged']);
  exit;
}

$cfg = require __DIR__ . '/../app/config.php';
require __DIR__ . '/../app/supabase.php';
$sb = new Supabase($cfg);

$ticketId = trim($_GET['ticket'] ?? $_GET['ticket_id'] ?? $_GET['id'] ?? $_GET['t'] ?? '');
if ($ticketId === '') {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'missing_ticket']);
  exit;
}

function norm_dir($d) {
  return strtoupper(trim((string)$d));
}

function pick_iso($m) {
  $iso = $m['created_at'] ?? null;
  if (!$iso) $iso = $m['sent_at'] ?? null;
  if (!$iso) $iso = gmdate('c');
  return $iso;
}

$r = $sb->request('GET', '/rest/v1/messages', [
  'select' => 'id,direction,text,created_at,sent_at,media_url,waha_message_id',
  'ticket_id' => 'eq.' . $ticketId,
  // ordem de chegada no sistema (mais estável)
  'order' => 'created_at.asc',
  'limit' => 200
]);

$rows = $r['json'] ?? [];
if (!is_array($rows)) $rows = [];

$out = [];

foreach ($rows as $m) {
  $text = trim((string)($m['text'] ?? ''));
  if ($text === '') continue; // remove “balões” vazios

  $dir = norm_dir($m['direction'] ?? '');
  // IN = cliente | OUT = atendente
  $fromMe = ($dir === 'OUT');

  $iso = pick_iso($m);
  $sec = strtotime($iso);
  if ($sec === false) $sec = time();

  $out[] = [
    'id' => $m['id'] ?? null,

    // compat: o front pode usar direction ou fromMe
    'direction' => $dir,
    'fromMe' => $fromMe,
    'from_me' => $fromMe,

    // texto
    'text' => $text,
    'body' => $text, // fallback pra telas antigas

    // timestamps (compatível com seu chat.php)
    'created_at' => $iso,
    'sent_at' => ($m['sent_at'] ?? null),
    'ts' => $iso,
    't'  => $sec,
    'ms' => $sec * 1000,
    'timestamp' => $sec,
    'timestampMs' => $sec * 1000,

    // extras se você quiser usar depois
    'media_url' => $m['media_url'] ?? null,
    'waha_message_id' => $m['waha_message_id'] ?? null,

    // pra mostrar horinha sem depender do front
    'time' => date('H:i', $sec),
  ];
}

echo json_encode([
  'ok' => true,
  'ticket_id' => $ticketId,
  'count' => count($out),
  'messages' => $out
]);