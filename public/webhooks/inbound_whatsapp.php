<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);

require __DIR__ . '/../../src/supabase.php';

header('Content-Type: application/json; charset=utf-8');

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'invalid_json']);
  exit;
}

/**
 * Normaliza formatos comuns vindos do n8n:
 * - Array: [ { ... } ]
 * - Wrapper: { body: { ... } }
 * - Direto: { ... }
 */
$root = $data;
if (is_array($root) && isset($root[0])) {
  $root = $root[0];
}

$event = $root['body'] ?? $root;

/**
 * =========================
 * 1) Pega customer vindo do n8n
 * =========================
 * Esperado no body:
 * customer: { id, affiliate_id, indicator_slug }
 */
$customerId    = (string)($event['customer']['id'] ?? '');
$affiliateId   = (string)($event['customer']['affiliate_id'] ?? '');
$indicatorSlug = (string)($event['customer']['indicator_slug'] ?? '');

if ($customerId === '') {
  echo json_encode([
    'ok' => true,
    'skipped' => true,
    'reason' => 'missing_customer_id',
    'debug_keys' => array_keys($event),
  ]);
  exit;
}

/**
 * =========================
 * 2) Tenta achar payload (apenas para pegar nome/texto, etc)
 * =========================
 */
$p =
  ($event['payload'] ?? null) ??
  ($event['body']['payload'] ?? null) ??
  ($root['payload'] ?? null) ??
  ($root['body']['payload'] ?? null) ??
  (is_array($root) && isset($root[0]['body']['payload']) ? $root[0]['body']['payload'] : null);

// se ainda não achou, tenta se o n8n mandou "data" contendo o evento
if (!$p && isset($event['data'])) {
  $maybe = is_string($event['data']) ? json_decode($event['data'], true) : $event['data'];
  if (is_array($maybe)) {
    $p = $maybe['payload'] ?? ($maybe['body']['payload'] ?? null);
  }
}

// se payload vier como string JSON (ex: do n8n), tenta decodificar
if (is_string($p)) {
  $decoded = json_decode($p, true);
  if (is_array($decoded)) {
    $p = $decoded['payload'] ?? $decoded;
  }
}
if (!is_array($p)) $p = [];

/**
 * =========================
 * 3) Extrai texto (bem tolerante)
 * =========================
 */
$msg = '';

// preferencial: message.text (o que você manda do n8n)
$msg = trim((string)($event['message']['text'] ?? ''));

// fallback: waha.text
if ($msg === '' && isset($event['waha']['text']) && is_string($event['waha']['text'])) {
  $msg = trim($event['waha']['text']);
}

// fallback: payload.body
if ($msg === '' && isset($p['body']) && is_string($p['body'])) {
  $msg = trim($p['body']);
}

// fallback: payload.payload.body
if ($msg === '' && isset($p['payload']['body']) && is_string($p['payload']['body'])) {
  $msg = trim($p['payload']['body']);
}

// fallback: payload.payload._data.body
if ($msg === '' && isset($p['payload']['_data']['body']) && is_string($p['payload']['_data']['body'])) {
  $msg = trim($p['payload']['_data']['body']);
}

// fallback: payload direto com _data.body
if ($msg === '' && isset($p['_data']['body']) && is_string($p['_data']['body'])) {
  $msg = trim($p['_data']['body']);
}

// fallback: text_raw/text
if ($msg === '' && isset($event['text_raw']) && is_string($event['text_raw'])) {
  $msg = trim($event['text_raw']);
}
if ($msg === '' && isset($event['text']) && is_string($event['text'])) {
  $msg = trim($event['text']);
}

/**
 * =========================
 * 4) Nome do cliente
 * =========================
 * Preferir o que n8n manda -> cair no WAHA -> fallback
 */
$notifyName = trim((string)($event['message']['notify_name'] ?? ''));
if ($notifyName === '') {
  $notifyName = trim((string)($event['waha']['notifyName'] ?? ''));
}
if ($notifyName === '' && isset($p['_data']['notifyName'])) {
  $notifyName = trim((string)$p['_data']['notifyName']);
}
if ($notifyName === '' || $notifyName === '.') {
  $notifyName = 'Cliente WhatsApp';
}

/**
 * =========================
 * 5) Phone / wa_from / wa_lid (opcional)
 * =========================
 * (Você pode usar isso só pra “preencher”, mas não é obrigatório)
 */
$from = (string)($event['message']['from'] ?? ($p['from'] ?? ''));
$wa_from = null;
$wa_lid  = null;
$phone   = null;

if ($from !== '') {
  // se vier só numero, anexa @c.us
  if (!str_contains($from, '@')) $from .= '@c.us';

  if (str_ends_with($from, '@c.us')) {
    $phone = preg_replace('/\D+/', '', str_replace('@c.us', '', $from));
    $wa_from = $phone; // seu supabase guarda wa_from como "5581..." (sem @)
  } elseif (str_ends_with($from, '@lid')) {
    $wa_lid = preg_replace('/\D+/', '', str_replace('@lid', '', $from));
  }
}

// se o n8n já mandar "from" limpo, melhor ainda:
if (isset($event['message']['from']) && is_string($event['message']['from'])) {
  $clean = trim($event['message']['from']);
  if ($clean !== '') $wa_from = $clean;
}


// ✅ se veio indicator_slug, ele manda no affiliate_id (se existir no banco)
if ($indicatorSlug !== '') {
  $foundAff = sb_request(
    'GET',
    "affiliates?select=id,instagram_username&instagram_username=eq." . urlencode(strtolower($indicatorSlug)) . "&limit=1",
    null,
    true
  );

  if ($foundAff && isset($foundAff[0]['id'])) {
    $affiliateId = (string)$foundAff[0]['id']; // <- sobrescreve SEM DISCUTIR
  }
}

/**
 * =========================
 * 6) UPDATE ONLY no Supabase
 * =========================
 * - NÃO cria customer
 * - só atualiza campos úteis
 */
try {
  $patch = [
    'last_message_at' => 'now()',
    'last_user_text'  => $msg,
    'source_text'     => $msg,
  ];

  // só atualiza se veio algo (não pisa com vazio)
  if ($notifyName !== '') $patch['name'] = $notifyName;
  if ($phone !== null && $phone !== '') $patch['phone'] = $phone;
  if ($wa_from !== null && $wa_from !== '') $patch['wa_from'] = $wa_from;
  if ($wa_lid !== null && $wa_lid !== '') $patch['wa_lid'] = $wa_lid;

  if ($affiliateId !== '') $patch['affiliate_id'] = $affiliateId;
  if ($indicatorSlug !== '') $patch['indicator_slug'] = $indicatorSlug;

  // PATCH /customers?id=eq.<uuid>
  $updated = sb_request(
    'PATCH',
    'customers?id=eq.' . urlencode($customerId),
    $patch,
    true
  );

  echo json_encode([
    'ok' => true,
    'updated' => true,
    'customer_id' => $customerId,
    'affiliate_id' => $affiliateId ?: null,
    'indicator_slug' => $indicatorSlug ?: null,
    'msg_preview' => mb_substr($msg, 0, 120),
  ]);
  exit;

} catch (Exception $e) {
  http_response_code(500);
  echo json_encode([
    'ok' => false,
    'error' => $e->getMessage(),
    'customer_id' => $customerId
  ]);
  exit;
}